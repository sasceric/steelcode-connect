<?php

namespace App\MessageHandler;

use App\Entity\IntegrationImportRun;
use App\Integration\ImportCancellation;
use App\Integration\ImportCancelledException;
use App\Integration\IntegrationImportLogger;
use App\Integration\ShopwareMediaImporter;
use App\Integration\ShopwareProductImporter;
use App\Integration\ShopwareProductRelationImporter;
use App\Integration\ShopwareReferenceImporter;
use App\Message\ImportShopwareProducts;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final class ImportShopwareProductsHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShopwareProductImporter $importer,
        private readonly ShopwareProductRelationImporter $relationImporter,
        private readonly ShopwareMediaImporter $mediaImporter,
        private readonly ShopwareReferenceImporter $referenceImporter,
        private readonly IntegrationImportLogger $importLogger,
        private readonly ManagerRegistry $managerRegistry,
        private readonly ImportCancellation $cancellation,
    ) {
    }

    public function __invoke(ImportShopwareProducts $message): void
    {
        $run = $this->entityManager->getRepository(IntegrationImportRun::class)
            ->find($message->runId);
        if (!$run instanceof IntegrationImportRun || $run->getStatus() !== 'queued') {
            return;
        }
        $runId = $run->getId();
        $ensureActive = function () use ($runId): void {
            $this->cancellation->throwIfCancelled($runId);
        };
        $reloadRun = function () use (&$run, $runId): IntegrationImportRun {
            if ($this->entityManager->contains($run)) {
                return $run;
            }

            $freshRun = $this->entityManager->find(IntegrationImportRun::class, $runId);
            if (!$freshRun instanceof IntegrationImportRun) {
                throw new \RuntimeException('The import run could not be reloaded.');
            }

            $run = $freshRun;

            return $run;
        };

        try {
            $ensureActive();
            $run->start(0, 'preparing');
            $this->importLogger->info(
                $run,
                'preparing',
                'integrationLog.preparing',
            );
            $this->entityManager->flush();

            $this->mediaImporter->import(
                $run,
                function (string $stage) use ($reloadRun, $ensureActive): void {
                    $ensureActive();
                    $currentRun = $reloadRun();
                    $currentRun->setCurrentStage($stage);
                    $this->importLogger->info(
                        $currentRun,
                        $stage,
                        'integrationLog.stageStarted',
                    );
                    $this->entityManager->flush();
                },
                function (
                    string $reason,
                    ?string $sourceReference,
                ) use ($reloadRun, $ensureActive): void {
                    $ensureActive();
                    $currentRun = $reloadRun();
                    $this->importLogger->warning(
                        $currentRun,
                        'media',
                        'integrationLog.mediaFailed',
                        array_filter([
                            'sourceReference' => $sourceReference,
                            'reason' => mb_substr($reason, 0, 1000),
                        ]),
                    );
                    $this->entityManager->flush();
                },
                $ensureActive,
            );
            $run = $reloadRun();

            $this->referenceImporter->import(
                $run,
                function (string $stage) use ($run, $ensureActive): void {
                    $ensureActive();
                    $run->setCurrentStage($stage);
                    $this->importLogger->info(
                        $run,
                        $stage,
                        'integrationLog.stageStarted',
                    );
                    $this->entityManager->flush();
                },
                $ensureActive,
            );
            $run = $reloadRun();

            $this->importer->import(
                $run,
                function (int $totalItems) use ($reloadRun, $ensureActive): void {
                    $ensureActive();
                    $currentRun = $reloadRun();
                    if ($currentRun->getCurrentStage() !== 'products') {
                        $currentRun->start($totalItems, 'products');
                        $this->importLogger->info(
                            $currentRun,
                            'products',
                            'integrationLog.productsStarted',
                            ['totalItems' => $totalItems],
                        );
                    } else {
                        $currentRun->updateTotalItems($totalItems);
                    }

                    $this->entityManager->flush();
                },
                function (
                    bool $created,
                    ?string $failureReason,
                    ?string $productReference,
                ) use ($reloadRun, $ensureActive): void {
                    $ensureActive();
                    $currentRun = $reloadRun();
                    if ($failureReason === null) {
                        $currentRun->recordSuccess($created);
                    } else {
                        $currentRun->recordFailure();
                        $this->importLogger->warning(
                            $currentRun,
                            'products',
                            'integrationLog.productFailed',
                            array_filter([
                                'productReference' => $productReference,
                                'reason' => mb_substr($failureReason, 0, 1000),
                            ]),
                        );
                    }
                    $this->entityManager->flush();
                },
                function (string $stage) use ($reloadRun, $ensureActive): void {
                    $ensureActive();
                    $currentRun = $reloadRun();
                    $currentRun->setCurrentStage($stage);
                    $this->importLogger->info(
                        $currentRun,
                        $stage,
                        'integrationLog.stageStarted',
                    );
                    $this->entityManager->flush();
                },
                $ensureActive,
            );
            $run = $reloadRun();
            $this->relationImporter->import(
                $run,
                function (string $stage) use ($reloadRun, $ensureActive): void {
                    $ensureActive();
                    $currentRun = $reloadRun();
                    $currentRun->setCurrentStage($stage);
                    $this->importLogger->info(
                        $currentRun,
                        $stage,
                        'integrationLog.stageStarted',
                    );
                    $this->entityManager->flush();
                },
                $ensureActive,
            );
            $run = $reloadRun();
            $ensureActive();
            $run->complete();
            $this->importLogger->info(
                $run,
                'completed',
                'integrationLog.completed',
                [
                    'createdItems' => $run->getCreatedItems(),
                    'updatedItems' => $run->getUpdatedItems(),
                    'failedItems' => $run->getFailedItems(),
                ],
            );
            $this->entityManager->flush();
        } catch (ImportCancelledException) {
            return;
        } catch (\Throwable $exception) {
            $entityManager = $this->openEntityManager();
            if (!$entityManager->contains($run)) {
                $freshRun = $entityManager->find(IntegrationImportRun::class, $runId);
                if (!$freshRun instanceof IntegrationImportRun) {
                    throw $exception;
                }

                $run = $freshRun;
            }
            if ($run->getStatus() === 'cancelled') {
                return;
            }
            $run->fail($exception->getMessage());
            $this->importLogger->error(
                $run,
                'failed',
                'integrationLog.failed',
                ['reason' => mb_substr($exception->getMessage(), 0, 1000)],
            );
            $entityManager->flush();

            throw new UnrecoverableMessageHandlingException(
                'The Shopware import could not be completed.',
                0,
                $exception,
            );
        }
    }

    private function openEntityManager(): EntityManagerInterface
    {
        if ($this->entityManager->isOpen()) {
            return $this->entityManager;
        }

        $entityManager = $this->managerRegistry->resetManager();
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \RuntimeException('The database manager could not be reset after an import failure.');
        }

        return $entityManager;
    }
}
