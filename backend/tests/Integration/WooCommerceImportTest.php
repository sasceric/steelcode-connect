<?php

namespace App\Tests\Integration;

use App\Entity\Customer;
use App\Entity\IntegrationConnection;
use App\Entity\IntegrationEntityMapping;
use App\Entity\InventoryMovement;
use App\Entity\Locale;
use App\Entity\Product;
use App\Entity\ProductPropertyAssignment;
use App\Entity\SalesOrder;
use App\Entity\Tenant;
use App\Entity\Tax;
use App\Integration\WooCommerceCatalogueImporter;
use App\Integration\WooCommerceSalesMapper;
use App\Service\SalesOrderIngestionService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WooCommerceImportTest extends KernelTestCase
{
    public function testReimportsAreTenantScopedAndHistoricalOrdersDoNotChangeStock(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $locale = $manager->getRepository(Locale::class)->findOneBy(['code' => 'en-GB']);
            if (!$locale instanceof Locale) {
                $manager->persist(new Locale('en-GB', 'English', 'English'));
            }
            $tenant = new Tenant('Woo import regression');
            $otherTenant = new Tenant('Other Woo tenant');
            $connection = new IntegrationConnection(
                $tenant,
                'woocommerce',
                'Woo test',
                ['source', 'channel'],
            );
            $connection->activate('Test connection');
            $otherConnection = new IntegrationConnection(
                $otherTenant,
                'woocommerce',
                'Other Woo',
                ['source', 'channel'],
            );
            foreach ([
                $tenant,
                $otherTenant,
                $connection,
                $otherConnection,
            ] as $entity) {
                $manager->persist($entity);
            }
            $manager->flush();
            $catalogue = self::getContainer()->get(WooCommerceCatalogueImporter::class);
            $source = [
                'id' => 101,
                'sku' => 'WOO-REGRESSION',
                'name' => 'Test product',
                'type' => 'simple',
                'attributes' => [['id' => 0, 'name' => 'Color', 'options' => ['Blue', 'Blue']]],
            ];
            self::assertTrue(
                $catalogue->product(
                    $source,
                    $connection,
                    $manager,
                    [],
                    [],
                ),
            );
            $manager->flush();
            self::assertFalse(
                $catalogue->product(
                    $source,
                    $connection,
                    $manager,
                    [],
                    [],
                ),
            );
            $manager->flush();
            $product = $catalogue->mapped(
                $connection,
                'product',
                '101',
                Product::class,
                $manager,
            );
            self::assertInstanceOf(Product::class, $product);
            self::assertSame(
                1,
                $manager->getRepository(ProductPropertyAssignment::class)->count(['product' => $product]),
            );
            self::assertNull(
                $catalogue->mapped(
                    $otherConnection,
                    'product',
                    '101',
                    Product::class,
                    $manager,
                ),
            );
            $tax = new Tax($tenant, 'Test tax', '20.00');
            $manager->persist($tax);
            $product->updateReferences($tax, null, null, null, null);
            $manager->flush();
            $variantSource = [
                'id' => 102,
                'sku' => 'WOO-REGRESSION',
                'name' => 'Blue variant',
                'attributes' => [['id' => 0, 'name' => 'Color', 'option' => 'Blue']],
            ];
            self::assertTrue(
                $catalogue->product(
                    $variantSource,
                    $connection,
                    $manager,
                    [],
                    [],
                    '101',
                ),
            );
            $manager->flush();
            $variant = $catalogue->mapped(
                $connection,
                'product',
                '102',
                Product::class,
                $manager,
            );
            self::assertNotSame($product->getId()->toRfc4122(), $variant->getId()->toRfc4122());
            self::assertSame($product->getId()->toRfc4122(), $variant->getParent()->getId()->toRfc4122());
            self::assertNotSame($product->getSku(), $variant->getSku());
            self::assertSame($tax->getId()->toRfc4122(), $variant->getTax()?->getId()->toRfc4122());
            self::assertFalse(
                $catalogue->product(
                    $variantSource,
                    $connection,
                    $manager,
                    [],
                    [],
                    '101',
                ),
            );
            $manager->flush();
            // A corrupted cross-tenant mapping must not expose the other tenant's product.
            $manager->persist(
                new IntegrationEntityMapping(
                    $otherTenant,
                    $otherConnection,
                    'product',
                    '101',
                    $product->getId(),
                ),
            );
            $manager->flush();
            self::assertNull(
                $catalogue->mapped(
                    $otherConnection,
                    'product',
                    '101',
                    Product::class,
                    $manager,
                ),
            );
            $mapper = new WooCommerceSalesMapper();
            $sales = self::getContainer()->get(SalesOrderIngestionService::class);
            $customerSource = [
                'id' => 1,
                'email' => 'current@example.test',
                'billing' => ['address_1' => 'Current street'],
            ];
            $customer = $sales->ingestCustomer(
                $connection,
                $mapper->customer($customerSource),
                $mapper->address($customerSource['billing'], 'billing'),
                [],
                $manager,
                [],
                true,
            );
            $manager->flush();
            $event = $mapper->order(
                [
                    'id' => 201,
                    'customer_id' => 1,
                    'currency' => 'EUR',
                    'status' => 'completed',
                    'total' => '24.00',
                    'total_tax' => '4.00',
                    'billing' => ['email' => 'historical@example.test', 'address_1' => 'Old street'],
                    'payment_method' => 'bacs',
                    'date_paid' => '2026-09-01T12:00:00',
                    'line_items' => [
                        [
                            'id' => 9,
                            'sku' => $product->getSku(),
                            'name' => 'Old name',
                            'quantity' => 2,
                            'total' => '20.00',
                            'total_tax' => '4.00',
                        ],
                    ],
                ],
            );
            $movements = $manager->getRepository(InventoryMovement::class)->count(['tenant' => $tenant]);
            $order = $sales->ingest(
                $connection,
                $event,
                $manager,
                false,
            );
            $sales->ingest(
                $connection,
                $event,
                $manager,
                false,
            );
            $manager->flush();
            self::assertSame($customer->getId()->toRfc4122(), $order->getCustomer()?->getId()->toRfc4122());
            self::assertSame('current@example.test', $customer->getEmail());
            self::assertSame('historical@example.test', $order->getCustomerSnapshot()['email']);
            self::assertSame(1, $manager->getRepository(Customer::class)->count(['connection' => $connection]));
            self::assertSame(1, $manager->getRepository(SalesOrder::class)->count(['connection' => $connection]));
            self::assertSame(
                $movements,
                $manager->getRepository(InventoryMovement::class)->count(['tenant' => $tenant]),
            );
            self::assertCount(1, $order->getPayments());
        } finally {
            if ($database->isTransactionActive()) {
                $database->rollBack();
            }
        }
    }
}
