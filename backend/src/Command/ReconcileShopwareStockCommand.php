<?php

namespace App\Command;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationSalesSyncCursor;
use App\Entity\IntegrationSecret;
use App\Entity\InventoryLevel;
use App\Entity\InventorySyncOutbox;
use App\Entity\ProductChannelPublication;
use App\Integration\SecretCipher;
use App\Integration\ShopwareClient;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:stock-sync:reconcile-shopware',
    description: 'Publishes queued stock to one Shopware connection in retryable batches.',
)]
final class ReconcileShopwareStockCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecretCipher $cipher,
        private readonly ShopwareClient $shopware,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('connection-id', InputArgument::REQUIRED, 'Shopware integration connection ID.')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Products per Shopware request.', '100')
            ->addOption('verify', null, InputOption::VALUE_NONE, 'Read back every published leaf product from Shopware.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->entityManager->getRepository(IntegrationConnection::class)->find(
            (string) $input->getArgument('connection-id'),
        );
        if (!$connection instanceof IntegrationConnection) {
            $output->writeln('<error>Connection not found.</error>');

            return Command::FAILURE;
        }

        $settings = $connection->getConfiguration()['importSettings'] ?? [];
        if (
            !$connection->isEnabled()
            || $connection->getStatus() !== 'active'
            || $connection->getConnectorKey() !== 'shopware'
            || !is_array($settings)
            || ($settings['stockAuthority'] ?? null) !== 'connect'
            || ($settings['salesContinuousSync'] ?? false) !== true
        ) {
            $output->writeln('<error>Shopware connection is not active with Sales sync and Connect stock authority.</error>');

            return Command::FAILURE;
        }

        foreach ($this->entityManager->getRepository(IntegrationConnection::class)->findBy([
            'tenant' => $connection->getTenant(),
            'enabled' => true,
            'status' => 'active',
        ]) as $otherConnection) {
            if (
                $otherConnection->getId() != $connection->getId()
                && ($otherConnection->getConfiguration()['importSettings']['stockAuthority'] ?? null) === 'connect'
            ) {
                $output->writeln('<error>Bulk reconciliation requires a single stock-authoritative connection for this tenant.</error>');

                return Command::FAILURE;
            }
        }

        $cursor = $this->entityManager->getRepository(IntegrationSalesSyncCursor::class)->findOneBy([
            'connection' => $connection,
        ]);
        $startedAt = $settings['salesContinuousStartedAt'] ?? null;
        if (
            !$cursor instanceof IntegrationSalesSyncCursor
            || !is_string($startedAt)
            || $cursor->getStartedAt() != new \DateTimeImmutable($startedAt)
            || $cursor->getLastSyncedAt() <= $cursor->getStartedAt()
        ) {
            $output->writeln('<error>Run a successful Shopware Sales sync before publishing stock.</error>');

            return Command::FAILURE;
        }

        $secrets = [];
        foreach ($this->entityManager->getRepository(IntegrationSecret::class)->findBy(['connection' => $connection]) as $secret) {
            $secrets[$secret->getSecretKey()] = $this->cipher->decrypt(
                $secret->getCiphertext(),
                $secret->getNonce(),
            );
        }

        $batchSize = max(1, min(100, (int) $input->getOption('batch-size')));
        $processed = 0;
        $skipped = 0;
        $lastId = '';

        while (true) {
            $rows = $this->entityManager->getConnection()->fetchAllAssociative(
                <<<'SQL'
SELECT outbox.id
FROM inventory_sync_outbox outbox
WHERE outbox.tenant_id = :tenantId
  AND outbox.status IN ('pending', 'failed')
  AND outbox.id::text > :lastId
  AND EXISTS (
      SELECT 1
      FROM product_channel_publications publication
      INNER JOIN integration_sales_channels channel ON channel.id = publication.sales_channel_id
      WHERE publication.product_id = outbox.product_id
        AND channel.connection_id = :connectionId
        AND channel.active = TRUE
        AND publication.visibility > 0
  )
  AND NOT EXISTS (SELECT 1 FROM products variant WHERE variant.parent_id = outbox.product_id)
ORDER BY outbox.id
LIMIT :batchSize
SQL,
                [
                    'tenantId' => $connection->getTenant()->getId()->toRfc4122(),
                    'connectionId' => $connection->getId()->toRfc4122(),
                    'lastId' => $lastId,
                    'batchSize' => $batchSize,
                ],
                ['batchSize' => \Doctrine\DBAL\ParameterType::INTEGER],
            );
            if ($rows === []) {
                break;
            }

            $lastId = (string) $rows[array_key_last($rows)]['id'];
            $snapshots = [];
            $products = [];
            foreach ($rows as $row) {
                $event = $this->entityManager->find(InventorySyncOutbox::class, $row['id']);
                if (!$event instanceof InventorySyncOutbox) {
                    continue;
                }
                if ($cursor->getLastSyncedAt() <= $event->getUpdatedAt()) {
                    $output->writeln('<error>Sales sync has not passed all queued stock changes. Poll Sales and retry.</error>');

                    return Command::FAILURE;
                }

                $quantity = $this->availableQuantity($event);
                $publications = $this->entityManager->getRepository(ProductChannelPublication::class)->findBy([
                    'tenant' => $connection->getTenant(),
                    'product' => $event->getProduct(),
                ]);
                $productIds = [];
                foreach ($publications as $publication) {
                    if (
                        $publication->getSalesChannel()->getConnection()->getId() != $connection->getId()
                        || !$publication->getSalesChannel()->isActive()
                        || $publication->getVisibility() === 0
                    ) {
                        continue;
                    }
                    $externalId = $publication->getExternalProductId();
                    if ($externalId === null || $externalId === '') {
                        $output->writeln(sprintf('<error>Product %s has no Shopware ID.</error>', $event->getProduct()->getId()));

                        return Command::FAILURE;
                    }
                    $productIds[$externalId] = true;
                    $products[$externalId] = ['id' => $externalId, 'stock' => $quantity];
                }
                if ($productIds === []) {
                    ++$skipped;
                    continue;
                }

                $snapshots[] = [
                    'id' => $event->getId()->toRfc4122(),
                    'updatedAt' => $event->getUpdatedAt(),
                ];
            }

            try {
                $this->shopware->updateProductStocks(
                    (string) ($connection->getConfiguration()['baseUrl'] ?? ''),
                    $secrets,
                    array_values($products),
                );
            } catch (\Throwable $exception) {
                $output->writeln(sprintf('<error>Shopware batch failed: %s</error>', $exception->getMessage()));

                return Command::FAILURE;
            }

            $database = $this->entityManager->getConnection();
            $database->beginTransaction();
            try {
                foreach ($snapshots as $snapshot) {
                    $event = $this->entityManager->find(InventorySyncOutbox::class, $snapshot['id']);
                    if (!$event instanceof InventorySyncOutbox) {
                        continue;
                    }
                    $this->entityManager->lock($event, LockMode::PESSIMISTIC_WRITE);
                    $this->entityManager->refresh($event);
                    if (
                        in_array($event->getStatus(), ['pending', 'failed'], true)
                        && $event->getUpdatedAt() == $snapshot['updatedAt']
                    ) {
                        $event->markDispatched();
                        ++$processed;
                    } else {
                        ++$skipped;
                    }
                }
                $this->entityManager->flush();
                $database->commit();
            } catch (\Throwable $exception) {
                $database->rollBack();
                throw $exception;
            }

            $output->writeln(sprintf('Published %d stock event(s); %d skipped or requeued.', $processed, $skipped));
            $this->entityManager->clear();
            $connection = $this->entityManager->find(IntegrationConnection::class, $connection->getId());
            $cursor = $this->entityManager->getRepository(IntegrationSalesSyncCursor::class)->findOneBy([
                'connection' => $connection,
            ]);
        }

        $output->writeln(sprintf('Reconciliation complete: %d published, %d skipped or requeued.', $processed, $skipped));

        if ($input->getOption('verify')) {
            return $this->verifyShopware($connection, $secrets, $output);
        }

        return Command::SUCCESS;
    }

    /** @param array<string, string> $secrets */
    private function verifyShopware(
        IntegrationConnection $connection,
        array $secrets,
        OutputInterface $output,
    ): int
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
SELECT mapping.external_product_id,
       GREATEST(0, FLOOR(COALESCE(SUM(
           CASE WHEN warehouse.active = TRUE AND warehouse.fulfillment_enabled = TRUE
                THEN level.quantity - level.reserved_quantity - level.unavailable_quantity
                ELSE 0 END
       ), 0)))::int AS expected_stock
FROM (
    SELECT DISTINCT publication.product_id, publication.external_product_id
    FROM product_channel_publications publication
    INNER JOIN integration_sales_channels channel ON channel.id = publication.sales_channel_id
    WHERE channel.connection_id = :connectionId
      AND channel.active = TRUE
      AND publication.visibility > 0
      AND publication.external_product_id IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM products variant WHERE variant.parent_id = publication.product_id)
) mapping
LEFT JOIN inventory_levels level ON level.product_id = mapping.product_id
LEFT JOIN warehouses warehouse ON warehouse.id = level.warehouse_id
GROUP BY mapping.product_id, mapping.external_product_id
SQL,
            ['connectionId' => $connection->getId()->toRfc4122()],
        );

        $expected = [];
        foreach ($rows as $row) {
            $id = (string) $row['external_product_id'];
            if (isset($expected[$id]) && $expected[$id] !== (int) $row['expected_stock']) {
                $output->writeln(sprintf('<error>Conflicting stock mappings for Shopware product %s.</error>', $id));

                return Command::FAILURE;
            }
            $expected[$id] = (int) $row['expected_stock'];
        }

        $total = count($expected);
        $mismatches = [];
        $this->shopware->forEachEntityPage(
            (string) ($connection->getConfiguration()['baseUrl'] ?? ''),
            $secrets,
            'product',
            function (int $reportedTotal, array $products) use (&$expected, &$mismatches): void {
                foreach ($products as $product) {
                    $id = $product['id'] ?? null;
                    if (!is_string($id) || !isset($expected[$id])) {
                        continue;
                    }
                    $attributes = is_array($product['attributes'] ?? null)
                        ? $product['attributes']
                        : $product;
                    $actual = $attributes['stock'] ?? null;
                    if (!is_numeric($actual) || (int) $actual !== $expected[$id]) {
                        $mismatches[] = sprintf(
                            '%s: expected %d, Shopware %s',
                            $id,
                            $expected[$id],
                            is_scalar($actual) ? (string) $actual : 'missing',
                        );
                    }
                    unset($expected[$id]);
                }
            },
            [],
            250,
            ['includes' => ['product' => ['id', 'stock']]],
        );

        foreach (array_keys($expected) as $id) {
            $mismatches[] = sprintf('%s: published product missing from Shopware', $id);
        }

        foreach (array_slice($mismatches, 0, 20) as $mismatch) {
            $output->writeln('<error>'.$mismatch.'</error>');
        }
        $output->writeln(sprintf(
            'Verified %d published leaf product(s); %d mismatch(es).',
            $total,
            count($mismatches),
        ));

        return $mismatches === [] ? Command::SUCCESS : Command::FAILURE;
    }

    private function availableQuantity(InventorySyncOutbox $event): int
    {
        $available = 0.0;
        $levels = $this->entityManager->getRepository(InventoryLevel::class)->findBy([
            'tenant' => $event->getTenant(),
            'product' => $event->getProduct(),
        ]);
        foreach ($levels as $level) {
            if ($level->getWarehouse()->isActive() && $level->getWarehouse()->isFulfillmentEnabled()) {
                $available += (float) $level->getAvailableQuantity();
            }
        }

        return max(0, (int) floor($available));
    }
}
