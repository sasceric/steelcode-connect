<?php

namespace App\MessageHandler;

use App\Entity\Customer;
use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSecret;
use App\Entity\SalesOrder;
use App\Integration\ImportCancellation;
use App\Integration\ImportCancelledException;
use App\Integration\IntegrationImportLogger;
use App\Integration\SecretCipher;
use App\Integration\ShopwareClient;
use App\Integration\ShopwareSalesMapper;
use App\Integration\ShopwareSalesRecordIngestor;
use App\Message\ImportShopwareSales;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ImportShopwareSalesHandler
{
    private const PAGE_SIZE = 25;

    public function __construct(
        private readonly ManagerRegistry $managers,
        private readonly ShopwareClient $client,
        private readonly ShopwareSalesMapper $mapper,
        private readonly ShopwareSalesRecordIngestor $recordIngestor,
        private readonly SecretCipher $cipher,
        private readonly IntegrationImportLogger $logger,
        private readonly ImportCancellation $cancellation,
    ) {
    }

    public function __invoke(ImportShopwareSales $message): void
    {
        if (!Uuid::isValid($message->runId)) {
            return;
        }

        $manager = $this->manager();
        $run = $manager->find(IntegrationImportRun::class, Uuid::fromString($message->runId));
        if (!$run instanceof IntegrationImportRun || $run->getType() !== 'sales' || $run->getStatus() !== 'queued') {
            return;
        }

        $runId = $run->getId();
        $ensureActive = function () use ($runId): void {
            $this->cancellation->throwIfCancelled($runId);
        };

        try {
            $ensureActive();
            $connection = $run->getConnection();
            $settings = $connection->getConfiguration()['importSettings'] ?? [];
            $areas = is_array($settings['areas'] ?? null) ? $settings['areas'] : [];
            $customersEnabled = (bool) ($areas['salesCustomers'] ?? false);
            $ordersEnabled = (bool) ($areas['salesOrders'] ?? false);
            if (
                $connection->getConnectorKey() !== 'shopware'
                || !$connection->isEnabled()
                || $connection->getStatus() !== 'active'
                || !in_array('channel', $connection->getDirections(), true)
                || (!$customersEnabled && !$ordersEnabled)
            ) {
                throw new \DomainException('Shopware Sales import is not enabled for this connection.');
            }

            $baseUrl = $connection->getConfiguration()['baseUrl'] ?? null;
            if (!is_string($baseUrl) || trim($baseUrl) === '') {
                throw new \RuntimeException('The Shopware platform URL is not configured.');
            }

            $secrets = $this->secrets($manager, $connection);
            $run->start(0, 'preparing');
            $this->logger->info($run, 'preparing', 'integrationLog.salesPreparing');
            $manager->flush();

            if ($customersEnabled) {
                $this->importPages(
                    $manager,
                    $run,
                    $baseUrl,
                    $secrets,
                    'customer',
                    'customers',
                    $this->mapper->customerAssociations(),
                    [],
                    $ensureActive,
                    function (array $source, array $included, IntegrationConnection $currentConnection, EntityManagerInterface $currentManager): array {
                        $customer = $this->mapper->customer($source);
                        $existing = $currentManager->getRepository(Customer::class)->findOneBy([
                            'connection' => $currentConnection,
                            'externalId' => $customer['externalId'],
                        ]);
                        $this->recordIngestor->customer(
                            $source,
                            $included,
                            $currentConnection,
                            $currentManager,
                        );

                        return [
                            'created' => !$existing instanceof Customer,
                            'unresolvedSkus' => [],
                        ];
                    },
                );
            }

            if ($ordersEnabled) {
                $historyFrom = $settings['salesHistoryFrom'] ?? null;
                $criteria = [];
                if (is_string($historyFrom) && trim($historyFrom) !== '') {
                    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($historyFrom, 0, 10));
                    $dateErrors = \DateTimeImmutable::getLastErrors();
                    if (
                        !$date instanceof \DateTimeImmutable
                        || (is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                    ) {
                        throw new \InvalidArgumentException('The Sales history start date is invalid.');
                    }
                    $criteria['filter'] = [[
                        'type' => 'range',
                        'field' => 'orderDateTime',
                        'parameters' => ['gte' => $date->format('Y-m-d\T00:00:00.000P')],
                    ]];
                }
                $criteria['sort'] = [
                    ['field' => 'orderDateTime', 'order' => 'ASC'],
                    ['field' => 'id', 'order' => 'ASC'],
                ];

                $this->importPages(
                    $manager,
                    $run,
                    $baseUrl,
                    $secrets,
                    'order',
                    'orders',
                    $this->mapper->orderAssociations(),
                    $criteria,
                    $ensureActive,
                    function (array $source, array $included, IntegrationConnection $currentConnection, EntityManagerInterface $currentManager): array {
                        $event = $this->mapper->order($this->mapper->withIncluded($source, $included));
                        $existing = $currentManager->getRepository(SalesOrder::class)->findOneBy([
                            'connection' => $currentConnection,
                            'externalId' => $event['externalId'],
                        ]);
                        $order = $this->recordIngestor->order(
                            $source,
                            $included,
                            $currentConnection,
                            $currentManager,
                            true,
                        );

                        return [
                            'created' => !$existing instanceof SalesOrder,
                            'unresolvedSkus' => $order->getUnresolvedSkus(),
                        ];
                    },
                );
            }

            $ensureActive();
            $run->complete();
            $this->logger->info($run, 'completed', 'integrationLog.salesCompleted', [
                'createdItems' => $run->getCreatedItems(),
                'updatedItems' => $run->getUpdatedItems(),
                'failedItems' => $run->getFailedItems(),
            ]);
            $manager->flush();
        } catch (ImportCancelledException) {
            return;
        } catch (\Throwable $exception) {
            $manager = $this->manager();
            $run = $manager->find(IntegrationImportRun::class, $runId);
            if ($run instanceof IntegrationImportRun && $run->getStatus() !== 'cancelled') {
                $run->fail($exception->getMessage());
                $this->logger->error($run, 'failed', 'integrationLog.salesFailed', [
                    'reason' => mb_substr($exception->getMessage(), 0, 1000),
                ]);
                $manager->flush();
            }

            throw new UnrecoverableMessageHandlingException('The Shopware Sales import failed.', 0, $exception);
        }
    }

    /**
     * @param array<string, string> $secrets
     * @param array<string, mixed> $associations
     * @param array<string, mixed> $criteria
     * @param callable(): void $ensureActive
     * @param callable(array<string, mixed>, list<array<string, mixed>>, IntegrationConnection, EntityManagerInterface): array{created: bool, unresolvedSkus: list<string>} $importItem
     */
    private function importPages(
        EntityManagerInterface &$manager,
        IntegrationImportRun &$run,
        string $baseUrl,
        array $secrets,
        string $entity,
        string $stage,
        array $associations,
        array $criteria,
        callable $ensureActive,
        callable $importItem,
    ): void {
        $runId = $run->getId();
        $previousStageTotal = $run->getTotalItems();
        $run->setCurrentStage($stage);
        $this->logger->info($run, $stage, 'integrationLog.stageStarted');
        $manager->flush();

        $this->client->forEachEntityPage(
            $baseUrl,
            $secrets,
            $entity,
            function (int $total, array $items, array $included) use (
                &$manager,
                &$run,
                $runId,
                $stage,
                $previousStageTotal,
                $ensureActive,
                $importItem,
            ): void {
                $ensureActive();
                $run->updateTotalItems($previousStageTotal + $total);
                $manager->flush();

                foreach ($items as $source) {
                    $ensureActive();
                    $connection = $run->getConnection();
                    try {
                        $result = $importItem($source, $included, $connection, $manager);
                        if ($result['unresolvedSkus'] !== []) {
                            $run->recordFailure();
                            $this->logger->warning($run, $stage, 'integrationLog.salesSkuUnresolved', [
                                'sourceReference' => (string) ($source['id'] ?? 'unknown'),
                                'skus' => $result['unresolvedSkus'],
                            ]);
                        } else {
                            $run->recordSuccess($result['created']);
                        }
                    } catch (ImportCancelledException $exception) {
                        throw $exception;
                    } catch (\Throwable $exception) {
                        $manager = $this->manager();
                        $manager->clear();
                        $run = $manager->find(IntegrationImportRun::class, $runId);
                        if (!$run instanceof IntegrationImportRun) {
                            throw $exception;
                        }
                        $run->recordFailure();
                        $this->logger->warning($run, $stage, 'integrationLog.salesItemFailed', [
                            'sourceReference' => (string) ($source['id'] ?? 'unknown'),
                            'reason' => mb_substr($exception->getMessage(), 0, 1000),
                        ]);
                    }

                    $manager->flush();
                }

                $manager->clear();
                $run = $manager->find(IntegrationImportRun::class, $runId);
                if (!$run instanceof IntegrationImportRun) {
                    throw new \RuntimeException('The Sales import run could not be reloaded.');
                }
            },
            $associations,
            self::PAGE_SIZE,
            $criteria,
        );
    }

    /** @return array<string, string> */
    private function secrets(EntityManagerInterface $manager, IntegrationConnection $connection): array
    {
        $values = [];
        foreach ($manager->getRepository(IntegrationSecret::class)->findBy(['connection' => $connection]) as $secret) {
            $values[$secret->getSecretKey()] = $this->cipher->decrypt(
                $secret->getCiphertext(),
                $secret->getNonce(),
            );
        }

        return $values;
    }

    private function manager(): EntityManagerInterface
    {
        $manager = $this->managers->getManager();
        if (!$manager instanceof EntityManagerInterface) {
            throw new \RuntimeException('The database manager is unavailable.');
        }
        if (!$manager->isOpen()) {
            $manager = $this->managers->resetManager();
        }
        if (!$manager instanceof EntityManagerInterface) {
            throw new \RuntimeException('The database manager could not be reset.');
        }

        return $manager;
    }
}
