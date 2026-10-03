<?php

namespace App\MessageHandler;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSalesSyncCursor;
use App\Entity\IntegrationSecret;
use App\Entity\SalesOrder;
use App\Integration\SecretCipher;
use App\Integration\WooCommerceClient;
use App\Integration\WooCommerceSalesRecordIngestor;
use App\Message\SyncWooCommerceSalesConnection;
use App\Service\PendingSalesOrderProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class SyncWooCommerceSalesConnectionHandler
{
    public function __construct(
        private readonly ManagerRegistry $managers,
        private readonly WooCommerceClient $client,
        private readonly WooCommerceSalesRecordIngestor $records,
        private readonly PendingSalesOrderProcessor $pendingOrders,
        private readonly SecretCipher $cipher,
        private readonly LockFactory $locks,
        private readonly LoggerInterface $logger,
    )
    {
    }

    public function __invoke(SyncWooCommerceSalesConnection $message): void
    {
        if (!Uuid::isValid($message->tenantId) || !Uuid::isValid($message->connectionId)) {
            return;
        }
        // Shares the import lock: imports and live ingestion cannot race source identity.
        $lock = $this->locks->createLock('woo-import:' . $message->tenantId . ':' . $message->connectionId, 300);
        if (!$lock->acquire()) {
            throw new RecoverableMessageHandlingException('This WooCommerce connection is already being processed.');
        }
        $manager = $this->manager();
        try {
            $connection = $this->connection($message, $manager);
            if (!$connection instanceof IntegrationConnection) {
                return;
            }
            if ($manager->getRepository(IntegrationImportRun::class)->findOneBy(
                [
                    'tenant' => $connection->getTenant(),
                    'connection' => $connection,
                    'type' => 'sales',
                    'status' => ['queued', 'running'],
                ],
            ) instanceof IntegrationImportRun) {
                return;
            }
            $settings = $connection->getConfiguration()['importSettings'];
            $startedAt = new \DateTimeImmutable($settings['salesContinuousStartedAt']);
            $until = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $cursor = $manager->getRepository(IntegrationSalesSyncCursor::class)->findOneBy(['connection' => $connection]);
            if (!$cursor instanceof IntegrationSalesSyncCursor) {
                $cursor = new IntegrationSalesSyncCursor($connection, $startedAt);
                $manager->persist($cursor);
            } elseif ($cursor->getStartedAt() != $startedAt) {
                $cursor->restart($startedAt);
            }
            $manager->flush();
            $cursorId = $cursor->getId();
            $from = max($startedAt->modify('-1 second'), $cursor->getLastSyncedAt()->modify('-5 minutes'));
            $baseUrl = (string) ($connection->getConfiguration()['baseUrl'] ?? '');
            $secrets = [];
            foreach ($manager->getRepository(IntegrationSecret::class)->findBy(['connection' => $connection]) as $secret) {
                $secrets[$secret->getSecretKey()] = $this->cipher->decrypt($secret->getCiphertext(), $secret->getNonce());
            }
            $failed = false;
            // Both bounds are GMT. A one-second creation overlap handles Woo's
            // second precision; record-level timestamps enforce the true cutover.
            $query = [
                'after' => $startedAt->modify('-1 second')->format('Y-m-d\TH:i:s'),
                'before' => $until->format('Y-m-d\TH:i:s'),
                'modified_after' => $from->format('Y-m-d\TH:i:s'),
                'modified_before' => $until->format('Y-m-d\TH:i:s'),
                'dates_are_gmt' => 'true',
            ];
            $this->client->forEachPage(
                $baseUrl,
                $secrets,
                'orders',
                function (
                    int $total,
                    array $sources,
                ) use ($message, $manager, $lock, $startedAt, $baseUrl, $secrets, &$failed): void {
                    foreach ($sources as $source) {
                        $lock->refresh();
                        $current = $this->connection($message, $manager);
                        if (!$current instanceof IntegrationConnection) {
                            throw new \RuntimeException('WooCommerce ongoing Sales sync was disabled during processing.');
                        }
                        try {
                            $created = $source['date_created_gmt'] ?? null;
                            if (!is_string($created) || $created === '') {
                                throw new \InvalidArgumentException('WooCommerce order has no valid creation timestamp.');
                            }
                            if (new \DateTimeImmutable($created . 'Z') < $startedAt) {
                                continue;
                            }
                            $customerId = (int) ($source['customer_id'] ?? 0);
                            if ($customerId > 0 && ($current->getConfiguration()['importSettings']['areas']['salesCustomers'] ?? false)) {
                                $profile = $this->client->object(
                                    'GET',
                                    $baseUrl,
                                    $secrets,
                                    'customers/' . $customerId,
                                );
                                $this->records->customer($profile, $current, $manager);
                            }
                            $this->records->order(
                                $source,
                                $current,
                                $manager,
                                false,
                            );
                        } catch (\InvalidArgumentException|\DomainException) {
                            $failed = true;
                            $this->logger->error(
                                'WooCommerce Sales record could not be synchronized.',
                                [
                                    'tenantId' => $message->tenantId,
                                    'connectionId' => $message->connectionId,
                                    'externalId' => (string) ($source['id'] ?? 'unknown'),
                                ],
                            );
                        } finally {
                            $manager->clear();
                        }
                    }
                },
                $query,
            );
            if ($failed) {
                throw new \RuntimeException('WooCommerce Sales sync had record failures; the checkpoint was not advanced.');
            }
            $current = $this->connection($message, $manager);
            $cursor = $manager->find(IntegrationSalesSyncCursor::class, $cursorId);
            if (
                !$current instanceof IntegrationConnection
                || !$cursor instanceof IntegrationSalesSyncCursor
                || new \DateTimeImmutable($current->getConfiguration()['importSettings']['salesContinuousStartedAt']) != $startedAt
            ) {
                throw new \RuntimeException('The WooCommerce Sales sync context changed during processing.');
            }
            $this->pendingOrders->process(
                $current,
                $cursor,
                $manager,
                function (SalesOrder $order) use ($current, $manager, $lock): void {
                    $lock->refresh();
                    // Catalogue mappings may have arrived since order ingestion.
                    // Re-resolve by source ID before the shared allocation retry.
                    $source = $order->getSourcePayload()['sourceFields'] ?? [];
                    if ($source !== []) {
                        $this->records->order(
                            $source,
                            $current,
                            $manager,
                            false,
                        );
                    }
                },
            );
            $cursor->advance($until);
            $manager->flush();
        } catch (\Throwable $exception) {
            $manager = $this->manager();
            $connection = $this->connection($message, $manager);
            if ($connection instanceof IntegrationConnection) {
                $cursor = $manager->getRepository(IntegrationSalesSyncCursor::class)->findOneBy(['connection' => $connection]);
                if ($cursor instanceof IntegrationSalesSyncCursor) {
                    $cursor->fail(
                        'WooCommerce Sales synchronization failed. Check the worker and source API; checkpoint retained.',
                    );
                    $manager->flush();
                }
            }
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function connection(
        SyncWooCommerceSalesConnection $message,
        EntityManagerInterface $manager,
    ): ?IntegrationConnection
    {
        $connection = $manager->find(IntegrationConnection::class, Uuid::fromString($message->connectionId));
        if (!$connection instanceof IntegrationConnection || (string) $connection->getTenant()->getId() !== $message->tenantId) {
            return null;
        }
        $settings = $connection->getConfiguration()['importSettings'] ?? [];
        return $connection->getConnectorKey() === 'woocommerce' && $connection->isEnabled() && $connection->getStatus() === 'active' && in_array('channel', $connection->getDirections(), true) && ($settings['salesContinuousSync'] ?? false) === true && ($settings['areas']['salesOrders'] ?? false) === true && is_string($settings['salesContinuousStartedAt'] ?? null) && $settings['salesContinuousStartedAt'] !== '' ? $connection : null;
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
