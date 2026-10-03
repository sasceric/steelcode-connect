<?php

namespace App\MessageHandler;

use App\Entity\Customer;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSecret;
use App\Entity\SalesOrder;
use App\Integration\ImportCancellation;
use App\Integration\ImportCancelledException;
use App\Integration\IntegrationImportLogger;
use App\Integration\SecretCipher;
use App\Integration\WooCommerceCatalogueImporter;
use App\Integration\WooCommerceClient;
use App\Integration\WooCommerceSalesMapper;
use App\Integration\WooCommerceSalesRecordIngestor;
use App\Message\ImportWooCommerce;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ImportWooCommerceHandler
{
    public function __construct(
        private readonly ManagerRegistry $managers,
        private readonly WooCommerceClient $client,
        private readonly WooCommerceCatalogueImporter $catalogue,
        private readonly WooCommerceSalesMapper $mapper,
        private readonly WooCommerceSalesRecordIngestor $records,
        private readonly SecretCipher $cipher,
        private readonly ImportCancellation $cancellation,
        private readonly IntegrationImportLogger $logger,
        private readonly LockFactory $locks,
    )
    {
    }

    public function __invoke(ImportWooCommerce $message): void
    {
        if (!Uuid::isValid($message->runId)) {
            return;
        }
        $manager = $this->manager();
        $run = $manager->find(IntegrationImportRun::class, Uuid::fromString($message->runId));
        if (!$run instanceof IntegrationImportRun || $run->getStatus() !== 'queued') {
            return;
        }
        $connection = $run->getConnection();
        if (
            $connection->getConnectorKey() !== 'woocommerce'
            || !$connection->getTenant()->getId()->equals($run->getTenant()->getId())
            || !$connection->isEnabled()
            || $connection->getStatus() !== 'active'
        ) {
            $run->fail('The WooCommerce connection is not active or has an invalid tenant scope.');
            $manager->flush();
            return;
        }
        $lock = $this->locks->createLock('woo-import:' . $run->getTenant()->getId() . ':' . $connection->getId(), 300);
        if (!$lock->acquire()) {
            throw new RecoverableMessageHandlingException('Another import is running for this WooCommerce connection.');
        }
        $runId = $run->getId();
        $settings = $connection->getConfiguration()['importSettings'] ?? [];
        $areas = $settings['areas'] ?? [];
        $baseUrl = (string) ($connection->getConfiguration()['baseUrl'] ?? '');
        $secrets = [];
        $ensureActive = function () use ($runId, $lock): void {
            $this->cancellation->throwIfCancelled($runId);
            $lock->refresh();
        };
        $pages = function (
            string $resource,
            string $stage,
            callable $work,
            array $query = [],
        ) use (&$manager, &$run, $runId, $baseUrl, &$secrets, $ensureActive): void {
            $ensureActive();
            $offset = $run->getTotalItems();
            $run->setCurrentStage($stage);
            $this->logger->info($run, $stage, 'integrationLog.stageStarted');
            $manager->flush();
            $this->client->forEachPage(
                $baseUrl,
                $secrets,
                $resource,
                function (
                    int $total,
                    array $items,
                ) use (&$manager, &$run, $runId, $offset, $stage, $work, $ensureActive): void {
                    $run->updateTotalItems($offset + $total);
                    $manager->flush();
                    foreach ($items as $source) {
                        $ensureActive();
                        $database = $manager->getConnection();
                        $database->beginTransaction();
                        try {
                            $created = $work($source, $run->getConnection(), $manager);
                            $manager->flush();
                            $database->commit();
                            $run->recordSuccess($created);
                        } catch (ImportCancelledException $exception) {
                            $database->rollBack();
                            throw $exception;
                        } catch (\Throwable $exception) {
                            $database->rollBack();
                            // A closed ORM manager invalidates injected importer services.
                            // Stop safely rather than continue with detached state.
                            if (!$manager->isOpen()) {
                                throw new \RuntimeException('The WooCommerce import encountered a database error; inspect the run and retry.');
                            }
                            $manager->clear();
                            $run = $manager->find(IntegrationImportRun::class, $runId);
                            $run->recordFailure();
                            $this->logger->warning(
                                $run,
                                $stage,
                                'integrationLog.productFailed',
                                [
                                    'sourceReference' => (string) ($source['id'] ?? ''),
                                    'reason' => $exception instanceof \InvalidArgumentException || $exception instanceof \DomainException ? mb_substr($exception->getMessage(), 0, 500) : 'The source record could not be imported. Check its mapping and referenced media.',
                                ],
                            );
                        }
                        $manager->flush();
                    }
                    $manager->clear();
                    $run = $manager->find(IntegrationImportRun::class, $runId);
                },
                $query,
            );
        };
        try {
            foreach ($manager->getRepository(IntegrationSecret::class)->findBy(['connection' => $connection]) as $secret) {
                $secrets[$secret->getSecretKey()] = $this->cipher->decrypt($secret->getCiphertext(), $secret->getNonce());
            }
            $ensureActive();
            $run->start(0, 'preparing');
            $manager->flush();
            if ($run->getType() === 'sales') {
                if (!in_array('channel', $connection->getDirections(), true)) {
                    throw new \DomainException('This WooCommerce connection is not a Sales channel.');
                }
                if ($areas['salesCustomers'] ?? false) {
                    $pages(
                        'customers',
                        'customers',
                        function (
                            array $source,
                            $connection,
                            EntityManagerInterface $manager,
                        ): bool {
                            $profile = $this->mapper->customer($source);
                            $existing = $manager->getRepository(Customer::class)->findOneBy(['connection' => $connection, 'externalId' => $profile['externalId']]);
                            $this->records->customer($source, $connection, $manager);
                            return !$existing instanceof Customer;
                        },
                    );
                }
                if ($areas['salesOrders'] ?? false) {
                    $query = [];
                    if (!empty($settings['salesHistoryFrom'])) {
                        $query['after'] = substr($settings['salesHistoryFrom'], 0, 10) . 'T00:00:00';
                    }
                    $pages(
                        'orders',
                        'orders',
                        function (
                            array $source,
                            $connection,
                            EntityManagerInterface $manager,
                        ) use ($runId): bool {
                            $externalId = (string) ($source['id'] ?? '');
                            $existing = $manager->getRepository(SalesOrder::class)->findOneBy(['connection' => $connection, 'externalId' => $externalId]);
                            $order = $this->records->order($source, $connection, $manager, true);
                            if ($order->getUnresolvedSkus() !== []) {
                                // Keep the historical snapshot even when an old product is absent.
                                $this->logger->warning(
                                    $manager->find(IntegrationImportRun::class, $runId),
                                    'orders',
                                    'integrationLog.salesSkuUnresolved',
                                    ['sourceReference' => $externalId, 'skus' => $order->getUnresolvedSkus()],
                                );
                            }
                            return !$existing instanceof SalesOrder;
                        },
                        $query,
                    );
                }
            } elseif ($run->getType() === 'products') {
                $store = [];
                foreach (['settings/general', 'settings/tax'] as $resource) {
                    foreach ($this->client->page($baseUrl, $secrets, $resource)['items'] as $option) {
                        $store[$option['id']] = $option['value'] ?? null;
                    }
                }
                $store['_taxRates'] = [];
                if ($areas['taxes'] ?? true) {
                    $pages(
                        'taxes',
                        'taxes',
                        function (
                            array $source,
                            $connection,
                            $manager,
                        ) use (&$store): bool {
                            $store['_taxRates'][] = $source;
                            return $this->catalogue->reference(
                                'tax',
                                $source,
                                $connection,
                                $manager,
                            );
                        },
                    );
                } elseif ($areas['prices'] ?? true) {
                    // Read pricing context even when tax entity updates are disabled.
                    $this->client->forEachPage(
                        $baseUrl,
                        $secrets,
                        'taxes',
                        function (
                            int $total,
                            array $items,
                        ) use (&$store, $ensureActive): void {
                            $ensureActive();
                            array_push($store['_taxRates'], ...$items);
                        },
                    );
                }
                foreach ([
                    'categories' => ['category', 'categories'],
                    'tags' => ['tag', 'tags'],
                    'attributes' => ['property_group', 'properties'],
                    'brands' => ['brand', 'manufacturers'],
                ] as $resource => [$type, $area]) {
                    if (!($areas[$area] ?? true) && !($resource === 'attributes' && ($areas['variants'] ?? true))) {
                        continue;
                    }
                    if ($resource === 'brands') {
                        // WooCommerce core brands may be absent on older Woo releases.
                        try {
                            $this->client->page(
                                $baseUrl,
                                $secrets,
                                'products/brands',
                                ['per_page' => 1],
                            );
                        } catch (\RuntimeException $exception) {
                            if (!str_contains($exception->getMessage(), 'HTTP 404')) {
                                throw $exception;
                            }
                            continue;
                        }
                    }
                    $pages(
                        'products/' . $resource,
                        $resource === 'brands' ? 'brands' : $area,
                        fn(
                            array $source,
                            $connection,
                            $manager,
                        ): bool => $this->catalogue->reference(
                            $type,
                            $source,
                            $connection,
                            $manager,
                        ),
                    );
                }
                if (($areas['properties'] ?? true) || ($areas['variants'] ?? true)) {
                    $this->client->forEachPage(
                        $baseUrl,
                        $secrets,
                        'products/attributes',
                        function (int $total, array $attributes) use ($pages, $ensureActive): void {
                            foreach ($attributes as $attribute) {
                                $ensureActive();
                                $attributeId = (string) $attribute['id'];
                                $pages(
                                    'products/attributes/' . $attributeId . '/terms',
                                    'properties',
                                    fn(array $term, $connection, $manager): bool => $this->catalogue->term($attributeId, $term, $connection, $manager),
                                );
                            }
                        },
                    );
                }
                // Resolve category parents after every category mapping exists.
                if ($areas['categories'] ?? true) {
                    $this->client->forEachPage(
                        $baseUrl,
                        $secrets,
                        'products/categories',
                        function (
                            int $total,
                            array $items,
                        ) use (&$run, &$manager, $ensureActive): void {
                            foreach ($items as $source) {
                                $ensureActive();
                                $this->catalogue->reference(
                                    'category',
                                    $source,
                                    $run->getConnection(),
                                    $manager,
                                );
                                $manager->flush();
                            }
                        },
                    );
                }
                $pages(
                    'products',
                    'products',
                    fn(
                        array $source,
                        $connection,
                        $manager,
                    ): bool => $this->catalogue->product(
                        $source,
                        $connection,
                        $manager,
                        $store,
                        $areas,
                    ),
                );
                if ($areas['variants'] ?? true) {
                    $this->client->forEachPage(
                        $baseUrl,
                        $secrets,
                        'products',
                        function (
                            int $total,
                            array $items,
                        ) use ($pages, $store, $areas, $ensureActive): void {
                            foreach ($items as $parent) {
                                $ensureActive();
                                if (($parent['type'] ?? '') !== 'variable') {
                                    continue;
                                }
                                $parentId = (string) $parent['id'];
                                $pages(
                                    'products/' . $parentId . '/variations',
                                    'variants',
                                    fn(
                                        array $source,
                                        $connection,
                                        $manager,
                                    ): bool => $this->catalogue->product(
                                        $source,
                                        $connection,
                                        $manager,
                                        $store,
                                        $areas,
                                        $parentId,
                                    ),
                                );
                            }
                        },
                        ['type' => 'variable'],
                    );
                }
                if ($areas['crossSellings'] ?? true) {
                    $pages(
                        'products',
                        'crossSellings',
                        fn(array $source, $connection, $manager): bool => $this->catalogue->relatedProducts($source, $connection, $manager),
                    );
                }
            } else {
                throw new \InvalidArgumentException('Unsupported WooCommerce import type.');
            }
            $ensureActive();
            $run->complete();
            $this->logger->info(
                $run,
                'completed',
                'integrationLog.completed',
                [
                    'createdItems' => $run->getCreatedItems(),
                    'updatedItems' => $run->getUpdatedItems(),
                    'failedItems' => $run->getFailedItems(),
                ],
            );
            $manager->flush();
        } catch (ImportCancelledException) {
            return;
        } catch (\Throwable $exception) {
            $manager = $this->manager();
            $run = $manager->find(IntegrationImportRun::class, $runId);
            if ($run instanceof IntegrationImportRun && $run->getStatus() !== 'cancelled') {
                $run->fail($exception->getMessage());
                $this->logger->error(
                    $run,
                    'failed',
                    'integrationLog.failed',
                    ['reason' => $exception->getMessage()],
                );
                $manager->flush();
            }
            throw new UnrecoverableMessageHandlingException('The WooCommerce import could not be completed.', 0, $exception);
        } finally {
            $lock->release();
        }
    }

    private function manager(): EntityManagerInterface
    {
        $manager = $this->managers->getManager();
        if (!$manager->isOpen()) {
            $manager = $this->managers->resetManager();
        }
        return $manager;
    }
}
