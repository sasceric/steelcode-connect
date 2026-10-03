<?php

namespace App\Tests\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationEntityMapping;
use App\Entity\IntegrationSalesChannel;
use App\Entity\Product;
use App\Entity\ProductChannelPublication;
use App\Entity\Tenant;
use Doctrine\DBAL\Schema\Schema;
use DoctrineMigrations\Version20261002101000;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

require_once dirname(__DIR__, 2).'/migrations/Version20261002101000.php';

final class PublicationStockIdentityMigrationTest extends KernelTestCase
{
    public function testRepairIsUnambiguousTenantScopedAndDoesNotOverwriteKnownIds(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $tenant = new Tenant('Stock identity migration');
            $foreign = new Tenant('Foreign identity migration');
            $connection = new IntegrationConnection($tenant, 'woocommerce', 'Fixture', ['channel'], []);
            $channel = new IntegrationSalesChannel($tenant, $connection, 'store', 'Store', 'storefront', true, []);
            $products = [];
            foreach (['repair', 'keep', 'ambiguous', 'foreign', 'invalid'] as $label) {
                $product = new Product($tenant);
                $products[$label] = $product;
                $manager->persist($product);
                $manager->persist(new ProductChannelPublication($tenant, $product, $channel, 30, $label === 'keep' ? '123' : null));
            }
            foreach ([$tenant, $foreign, $connection, $channel] as $entity) {
                $manager->persist($entity);
            }
            foreach ([
                new IntegrationEntityMapping($tenant, $connection, 'product', '111', $products['repair']->getId()),
                new IntegrationEntityMapping($tenant, $connection, 'product', '222', $products['keep']->getId()),
                new IntegrationEntityMapping($tenant, $connection, 'product', '333', $products['ambiguous']->getId()),
                new IntegrationEntityMapping($tenant, $connection, 'product', '334', $products['ambiguous']->getId()),
                new IntegrationEntityMapping($foreign, $connection, 'product', '444', $products['foreign']->getId()),
                new IntegrationEntityMapping($tenant, $connection, 'product', 'not-a-woo-id', $products['invalid']->getId()),
            ] as $mapping) {
                $manager->persist($mapping);
            }
            $manager->flush();
            $migration = new Version20261002101000($database, new NullLogger());
            $migration->up(new Schema());
            foreach ($migration->getSql() as $query) {
                $database->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
            }
            foreach (['repair' => '111', 'keep' => '123', 'ambiguous' => null, 'foreign' => null, 'invalid' => null] as $label => $expected) {
                self::assertSame($expected, $database->fetchOne(
                    'SELECT external_product_id FROM product_channel_publications WHERE tenant_id = :tenant AND product_id = :product',
                    ['tenant' => (string) $tenant->getId(), 'product' => (string) $products[$label]->getId()],
                ));
            }
            foreach ($migration->getSql() as $query) {
                self::assertSame(0, $database->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes()), 'The repair is idempotent.');
            }
        } finally {
            $database->rollBack();
            $manager->clear();
        }
    }
}
