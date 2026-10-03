<?php

namespace App\MessageHandler;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSalesSyncCursor;
use App\Entity\IntegrationSecret;
use App\Integration\SecretCipher;
use App\Integration\ShopwareClient;
use App\Integration\ShopwareSalesMapper;
use App\Integration\ShopwareSalesRecordIngestor;
use App\Message\SyncShopwareSalesConnection;
use App\Service\SalesOrderIngestionService;
use App\Service\PendingSalesOrderProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class SyncShopwareSalesConnectionHandler
{
    private const PAGE_SIZE = 25;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShopwareClient $client,
        private readonly ShopwareSalesMapper $mapper,
        private readonly ShopwareSalesRecordIngestor $records,
        private readonly SalesOrderIngestionService $ingestion,
        private readonly SecretCipher $cipher,
        private readonly LockFactory $locks,
        private readonly LoggerInterface $logger,
        private readonly ManagerRegistry $managers,
    ) {
    }

    public function __invoke(SyncShopwareSalesConnection $message): void
    {
        if (!Uuid::isValid($message->connectionId)) {
            return;
        }

        $lock = $this->locks->createLock('shopware-sales-sync-'.$message->connectionId, 3600);
        if (!$lock->acquire()) {
            return;
        }

        try {
            $this->sync($message->connectionId);
        } catch (\Throwable $exception) {
            $this->recordFailure($message->connectionId, $exception);

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function recordFailure(string $connectionId, \Throwable $exception): void
    {
        $this->logger->error('Shopware Sales synchronization failed.', [
            'connectionId' => $connectionId,
            'reason' => $exception->getMessage(),
        ]);

        try {
            $manager = $this->entityManager->isOpen()
                ? $this->entityManager
                : $this->managers->resetManager();
            if (!$manager instanceof EntityManagerInterface) {
                return;
            }
            $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
            if (!$connection instanceof IntegrationConnection) {
                return;
            }
            $cursor = $manager->getRepository(IntegrationSalesSyncCursor::class)->findOneBy([
                'connection' => $connection,
            ]);
            if ($cursor instanceof IntegrationSalesSyncCursor) {
                $cursor->fail($exception->getMessage());
                $manager->flush();
            }
        } catch (\Throwable) {
            // Preserve the original failure for Messenger retry and failure transport.
        }
    }

    private function sync(string $connectionId): void
    {
        $connection = $this->entityManager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
        if (!$connection instanceof IntegrationConnection || !$this->isEnabled($connection)) {
            return;
        }

        $activeImport = $this->entityManager->getRepository(IntegrationImportRun::class)->findOneBy([
            'connection' => $connection,
            'type' => 'sales',
            'status' => ['queued', 'running'],
        ]);
        if ($activeImport instanceof IntegrationImportRun) {
            return;
        }

        $settings = $connection->getConfiguration()['importSettings'];
        $startedAt = new \DateTimeImmutable($settings['salesContinuousStartedAt']);
        $startedAt = $startedAt->setTimezone(new \DateTimeZone('UTC'));
        $runStartedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $cursor = $this->entityManager->getRepository(IntegrationSalesSyncCursor::class)->findOneBy([
            'connection' => $connection,
        ]);
        if (!$cursor instanceof IntegrationSalesSyncCursor) {
            $cursor = new IntegrationSalesSyncCursor($connection, $startedAt);
            $this->entityManager->persist($cursor);
        } elseif ($cursor->getStartedAt() != $startedAt) {
            $cursor->restart($startedAt);
        }
        $this->entityManager->flush();

        $from = $cursor->getLastSyncedAt()->modify('-5 minutes');
        if ($from < $startedAt) {
            $from = $startedAt;
        }
        $baseUrl = (string) ($connection->getConfiguration()['baseUrl'] ?? '');
        if ($baseUrl === '') {
            throw new \RuntimeException('The Shopware platform URL is not configured.');
        }

        $secrets = $this->secrets($connection);
        $failed = false;
        if (($settings['areas']['salesCustomers'] ?? false) === true) {
            foreach (['createdAt', 'updatedAt'] as $field) {
                $this->scan(
                    $connection,
                    'customer',
                    $baseUrl,
                    $secrets,
                    $this->mapper->customerAssociations(),
                    $this->criteria($field, $from, $runStartedAt),
                    $failed,
                );
            }
        }
        foreach (['createdAt', 'updatedAt'] as $field) {
            $orderCriteria = $this->criteria($field, $from, $runStartedAt);
            if ($field === 'updatedAt') {
                $orderCriteria['filter'][] = [
                    'type' => 'range',
                    'field' => 'createdAt',
                    'parameters' => ['gte' => $startedAt->format(\DateTimeInterface::ATOM)],
                ];
            }
            $this->scan(
                $connection,
                'order',
                $baseUrl,
                $secrets,
                $this->mapper->orderAssociations(),
                $orderCriteria,
                $failed,
            );
        }
        $this->scanAssociatedOrderChanges(
            $connection,
            $baseUrl,
            $secrets,
            $from,
            $runStartedAt,
            $startedAt,
            $failed,
        );
        $this->retryPendingOrders($connectionId, $cursor);

        if ($failed) {
            throw new \RuntimeException('Shopware Sales sync had record failures; the checkpoint was not advanced.');
        }

        $cursor = $this->entityManager->find(IntegrationSalesSyncCursor::class, $cursor->getId());
        if (!$cursor instanceof IntegrationSalesSyncCursor) {
            throw new \RuntimeException('The Shopware Sales sync checkpoint was lost.');
        }
        $cursor->advance($runStartedAt);
        $this->entityManager->flush();
    }

    private function retryPendingOrders(string $connectionId, IntegrationSalesSyncCursor $cursor): void
    {
        $cursor = $this->entityManager->find(IntegrationSalesSyncCursor::class, $cursor->getId());
        $connection = $this->entityManager->find(IntegrationConnection::class, Uuid::fromString($connectionId));
        if (!$cursor instanceof IntegrationSalesSyncCursor || !$connection instanceof IntegrationConnection) {
            throw new \RuntimeException('The Shopware Sales sync context was removed.');
        }

        (new PendingSalesOrderProcessor($this->ingestion))->process($connection, $cursor, $this->entityManager);
    }

    /**
     * @param array<string, string> $secrets
     * @param array<string, mixed> $associations
     * @param array<string, mixed> $criteria
     */
    private function scan(
        IntegrationConnection $connection,
        string $entity,
        string $baseUrl,
        array $secrets,
        array $associations,
        array $criteria,
        bool &$failed,
    ): void {
        $connectionId = $connection->getId();
        $this->client->forEachEntityPage(
            $baseUrl,
            $secrets,
            $entity,
            function (int $total, array $items, array $included) use ($connectionId, $entity, &$failed): void {
                foreach ($items as $source) {
                    try {
                        $currentConnection = $this->entityManager->find(IntegrationConnection::class, $connectionId);
                        if (!$currentConnection instanceof IntegrationConnection) {
                            throw new \RuntimeException('The Shopware connection was removed during Sales sync.');
                        }
                        if ($entity === 'customer') {
                            $this->records->customer($source, $included, $currentConnection, $this->entityManager);
                        } else {
                            $this->records->order($source, $included, $currentConnection, $this->entityManager, false);
                        }
                    } catch (\InvalidArgumentException|\DomainException $exception) {
                        $failed = true;
                        $this->logger->error('Shopware Sales record could not be synchronized.', [
                            'entity' => $entity,
                            'externalId' => (string) ($source['id'] ?? 'unknown'),
                            'connectionId' => $connectionId->toRfc4122(),
                            'reason' => $exception->getMessage(),
                        ]);
                    } finally {
                        // A failed record rolled back its transaction; discard its managed entities
                        // before another order can flush them by accident.
                        $this->entityManager->clear();
                    }
                }
            },
            $associations,
            self::PAGE_SIZE,
            $criteria,
        );
    }

    /** @param array<string, string> $secrets */
    private function scanAssociatedOrderChanges(
        IntegrationConnection $connection,
        string $baseUrl,
        array $secrets,
        \DateTimeImmutable $from,
        \DateTimeImmutable $until,
        \DateTimeImmutable $startedAt,
        bool &$failed,
    ): void {
        foreach (['order-delivery', 'order-transaction', 'order-line-item'] as $entity) {
            $this->client->forEachEntityPage(
                $baseUrl,
                $secrets,
                $entity,
                function (int $total, array $items) use (
                    $connection,
                    $baseUrl,
                    $secrets,
                    $startedAt,
                    &$failed,
                ): void {
                    $orderIds = [];
                    foreach ($items as $item) {
                        $attributes = is_array($item['attributes'] ?? null) ? $item['attributes'] : $item;
                        $orderId = $attributes['orderId'] ?? null;
                        if (is_string($orderId) && $orderId !== '') {
                            $orderIds[$orderId] = true;
                        }
                    }
                    if ($orderIds === []) {
                        return;
                    }

                    $this->scan(
                        $connection,
                        'order',
                        $baseUrl,
                        $secrets,
                        $this->mapper->orderAssociations(),
                        [
                            'filter' => [
                                [
                                    'type' => 'equalsAny',
                                    'field' => 'id',
                                    'value' => array_keys($orderIds),
                                ],
                                [
                                    'type' => 'range',
                                    'field' => 'createdAt',
                                    'parameters' => ['gte' => $startedAt->format(\DateTimeInterface::ATOM)],
                                ],
                            ],
                        ],
                        $failed,
                    );
                },
                [],
                self::PAGE_SIZE,
                $this->criteria('updatedAt', $from, $until),
            );
        }
    }

    /** @return array<string, mixed> */
    private function criteria(string $field, \DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        return [
            'filter' => [[
                'type' => 'range',
                'field' => $field,
                'parameters' => [
                    'gte' => $from->format(\DateTimeInterface::ATOM),
                    'lte' => $until->format(\DateTimeInterface::ATOM),
                ],
            ]],
            'sort' => [
                ['field' => $field, 'order' => 'ASC'],
                ['field' => 'id', 'order' => 'ASC'],
            ],
        ];
    }

    private function isEnabled(IntegrationConnection $connection): bool
    {
        $settings = $connection->getConfiguration()['importSettings'] ?? [];

        return $connection->getConnectorKey() === 'shopware'
            && $connection->isEnabled()
            && $connection->getStatus() === 'active'
            && in_array('channel', $connection->getDirections(), true)
            && is_array($settings)
            && ($settings['salesContinuousSync'] ?? false) === true
            && ($settings['areas']['salesOrders'] ?? false) === true
            && is_string($settings['salesContinuousStartedAt'] ?? null)
            && $settings['salesContinuousStartedAt'] !== '';
    }

    /** @return array<string, string> */
    private function secrets(IntegrationConnection $connection): array
    {
        $secrets = [];
        foreach ($this->entityManager->getRepository(IntegrationSecret::class)->findBy(['connection' => $connection]) as $secret) {
            $secrets[$secret->getSecretKey()] = $this->cipher->decrypt(
                $secret->getCiphertext(),
                $secret->getNonce(),
            );
        }

        return $secrets;
    }
}
