<?php

namespace App\Command;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationSecret;
use App\Entity\Product;
use App\Entity\ProductTranslation;
use App\Integration\SecretCipher;
use App\Integration\WooCommerceCatalogueImporter;
use App\Integration\WooCommerceClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:woocommerce:backfill-brands',
    description: 'Repairs brand assignments from stored Woo snapshots without replaying catalogue/media/stock imports.',
)]
final class BackfillWooCommerceBrandsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly WooCommerceCatalogueImporter $catalogue,
        private readonly WooCommerceClient $client,
        private readonly SecretCipher $cipher,
        private readonly LockFactory $locks,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant-id', InputArgument::REQUIRED, 'Tenant owning the connection.')
            ->addArgument('connection-id', InputArgument::REQUIRED, 'WooCommerce connection to repair.');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int
    {
        $tenantId = (string) $input->getArgument('tenant-id');
        $connectionId = (string) $input->getArgument('connection-id');
        if (!Uuid::isValid($tenantId) || !Uuid::isValid($connectionId)) {
            $output->writeln('<error>Invalid tenant or connection ID.</error>');
            return Command::INVALID;
        }
        $connection = $this->connection($tenantId, $connectionId);
        if (
            !$connection instanceof IntegrationConnection
            || $connection->getConnectorKey() !== 'woocommerce'
            || !$connection->isEnabled()
            || $connection->getStatus() !== 'active'
        ) {
            $output->writeln('<error>Active WooCommerce connection not found in this tenant.</error>');
            return Command::FAILURE;
        }
        $lock = $this->locks->createLock('woo-import:' . $tenantId . ':' . $connectionId, 300);
        if (!$lock->acquire()) {
            $output->writeln('<error>An import is already running for this connection.</error>');
            return Command::FAILURE;
        }
        try {
            $secrets = [];
            foreach ($this->manager->getRepository(IntegrationSecret::class)->findBy(['connection' => $connection]) as $secret) {
                $secrets[$secret->getSecretKey()] = $this->cipher->decrypt($secret->getCiphertext(), $secret->getNonce());
            }
            $this->client->forEachPage(
                $connection->getConfiguration()['baseUrl'],
                $secrets,
                'products/brands',
                function (
                    int $total,
                    array $sources,
                ) use ($connection, $lock): void {
                    $lock->refresh();
                    foreach ($sources as $source) {
                        $this->catalogue->reference(
                            'brand',
                            $source,
                            $connection,
                            $this->manager,
                        );
                        $this->manager->flush();
                    }
                },
            );
            $processed = 0;
            $skipped = 0;
            // Parents first, so variation snapshots without brands inherit current parent assignments.
            foreach ([false, true] as $variants) {
                $lastId = '00000000-0000-0000-0000-000000000000';
                do {
                    $rows = $this->manager->getConnection()->fetchFirstColumn(
                        'SELECT product.id FROM products product
                         JOIN integration_entity_mappings mapping ON mapping.local_id = product.id
                         WHERE mapping.tenant_id = :tenant AND mapping.connection_id = :connection
                           AND mapping.entity_type = :type AND product.tenant_id = :tenant
                           AND product.id > :lastId AND product.parent_id IS ' . ($variants ? 'NOT NULL' : 'NULL') . '
                         ORDER BY product.id LIMIT 25',
                        [
                            'tenant' => $tenantId,
                            'connection' => $connectionId,
                            'type' => 'product',
                            'lastId' => $lastId,
                        ],
                    );
                    if ($rows === []) {
                        break;
                    }
                    $lock->refresh();
                    $this->manager->getConnection()->beginTransaction();
                    try {
                        $connection = $this->connection($tenantId, $connectionId);
                        foreach ($rows as $productId) {
                            $product = $this->manager->getRepository(Product::class)->findOneBy([
                                'id' => Uuid::fromString($productId),
                                'tenant' => $connection->getTenant(),
                            ]);
                            $translations = $this->manager->getRepository(ProductTranslation::class)->findBy(['product' => $product]);
                            $source = null;
                            foreach ($translations as $translation) {
                                $candidate = $translation->getCustomFields()['_woocommerce'] ?? null;
                                if (is_array($candidate)) {
                                    $source = $candidate;
                                    break;
                                }
                            }
                            if ($source === null) {
                                $skipped += 1;
                                continue;
                            }
                            $this->catalogue->assignBrands(
                                $product,
                                $source,
                                $connection,
                                $this->manager,
                            );
                            $processed += 1;
                        }
                        $this->manager->flush();
                        $this->manager->getConnection()->commit();
                    } catch (\Throwable $exception) {
                        $this->manager->getConnection()->rollBack();
                        throw $exception;
                    }
                    $lastId = end($rows);
                    $this->manager->clear();
                } while (count($rows) === 25);
            }
            $output->writeln(
                sprintf(
                    'Brands repaired for %d products; %d without cached snapshots skipped. Manufacturers and inventory unchanged.',
                    $processed,
                    $skipped,
                ),
            );
            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function connection(
        string $tenantId,
        string $connectionId,
    ): ?IntegrationConnection
    {
        return $this->manager->getRepository(IntegrationConnection::class)->findOneBy(['id' => Uuid::fromString($connectionId), 'tenant' => Uuid::fromString($tenantId)]);
    }
}
