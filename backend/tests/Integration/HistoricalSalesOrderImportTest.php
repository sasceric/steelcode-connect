<?php

namespace App\Tests\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\Customer;
use App\Entity\SalesOrder;
use App\Entity\Tenant;
use App\Service\InventoryService;
use App\Service\InventorySyncOutboxService;
use App\Service\SalesOrderAllocationService;
use App\Service\SalesOrderIngestionService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class HistoricalSalesOrderImportTest extends KernelTestCase
{
    public function testOrderReimportDoesNotOverwriteCustomerProfileOrAddressBook(): void
    {
        self::bootKernel();
        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            $tenant = new Tenant('Customer import test');
            $connection = new IntegrationConnection(
                $tenant,
                'shopware',
                'Shopware test '.bin2hex(random_bytes(4)),
                ['source', 'channel'],
            );
            $connection->activate('Test connection');
            $entityManager->persist($tenant);
            $entityManager->persist($connection);
            $entityManager->flush();

            $service = new SalesOrderIngestionService(
                new SalesOrderAllocationService(
                    new InventoryService(new InventorySyncOutboxService()),
                ),
            );
            $customer = $service->ingestCustomer(
                $connection,
                [
                    'externalId' => 'customer-1',
                    'email' => 'current@example.test',
                    'firstName' => 'Current',
                    'customerNumber' => 'C-100',
                    'accountType' => 'business',
                    'vatIds' => ['DE123'],
                    'defaultBillingAddressId' => 'saved-2',
                    'defaultShippingAddressId' => 'saved-1',
                ],
                ['externalId' => 'saved-2', 'street' => 'Current billing'],
                ['externalId' => 'saved-1', 'street' => 'Current shipping'],
                $entityManager,
                [['externalId' => 'old-saved', 'street' => 'Old saved address']],
                true,
            );

            $event = [
                'type' => 'placed',
                'externalId' => 'order-1',
                'lines' => [[
                    'externalLineId' => 'line-1',
                    'sku' => 'OLD-SKU',
                    'name' => 'Old product',
                    'quantity' => 1,
                ]],
                'customer' => [
                    'externalId' => 'customer-1',
                    'email' => 'old@example.test',
                    'firstName' => 'Historical',
                ],
                'billingAddress' => ['externalId' => 'order-address', 'street' => 'Old street'],
                'shippingAddress' => ['externalId' => 'order-address', 'street' => 'Old street'],
            ];
            $service->ingest($connection, $event, $entityManager, false);
            $service->ingest($connection, $event, $entityManager, false);
            $service->ingestCustomer(
                $connection,
                [
                    'externalId' => 'customer-1',
                    'email' => 'current@example.test',
                    'customerNumber' => 'C-100',
                    'vatIds' => ['DE123'],
                    'defaultBillingAddressId' => 'saved-2',
                    'defaultShippingAddressId' => 'saved-1',
                ],
                ['externalId' => 'saved-2', 'street' => 'Current billing'],
                ['externalId' => 'saved-1', 'street' => 'Current shipping'],
                $entityManager,
                [],
                true,
            );

            self::assertSame('current@example.test', $customer->getEmail());
            self::assertSame('C-100', $customer->getCustomerNumber());
            self::assertSame(['DE123'], $customer->getVatIds());
            self::assertTrue($customer->isProfileImported());
            self::assertCount(2, $customer->getAddresses());
            self::assertCount(1, array_filter(
                $customer->getAddresses()->toArray(),
                static fn ($address): bool => $address->isBillingDefault(),
            ));

            $order = $entityManager->getRepository(SalesOrder::class)->findOneBy([
                'connection' => $connection,
                'externalId' => 'order-1',
            ]);
            self::assertInstanceOf(SalesOrder::class, $order);
            self::assertSame('old@example.test', $order->getCustomerSnapshot()['email']);
            self::assertSame('Old street', $order->getBillingAddressSnapshot()['street']);
            self::assertSame($customer->getId(), $order->getCustomer()?->getId());

            $guestEvent = $event;
            $guestEvent['externalId'] = 'guest-order';
            $guestEvent['customer'] = ['externalId' => null, 'email' => 'guest@example.test', 'guest' => true];
            $service->ingest($connection, $guestEvent, $entityManager, false);
            $service->ingest($connection, $guestEvent, $entityManager, false);
            self::assertCount(1, $entityManager->getRepository(Customer::class)->findBy(['connection' => $connection]));
        } finally {
            $database->rollBack();
        }
    }

    public function testUnmatchedHistoricalLineIsStoredWithoutReservingStock(): void
    {
        self::bootKernel();
        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            $tenant = new Tenant('Historical import test');
            $connection = new IntegrationConnection(
                $tenant,
                'shopware',
                'Shopware test '.bin2hex(random_bytes(4)),
                ['source', 'channel'],
            );
            $connection->activate('Test connection');
            $entityManager->persist($tenant);
            $entityManager->persist($connection);
            $entityManager->flush();

            $service = new SalesOrderIngestionService(
                new SalesOrderAllocationService(
                    new InventoryService(new InventorySyncOutboxService()),
                ),
            );
            $event = [
                'type' => 'placed',
                'externalId' => 'old-order-1',
                'externalNumber' => 'OLD-1001',
                'lines' => [
                    [
                        'externalLineId' => 'old-line-1',
                        'sku' => 'DISCONTINUED-SKU',
                        'name' => 'Discontinued product',
                        'quantity' => 2,
                    ],
                    [
                        'externalLineId' => 'discount-1',
                        'type' => 'promotion',
                        'name' => 'Loyalty discount',
                        'quantity' => 1,
                        'commercial' => ['totalGross' => -10],
                    ],
                ],
                'sourcePayload' => [
                    'shopwareOrderId' => 'old-order-1',
                    'state' => 'completed',
                ],
            ];
            $order = $service->ingest($connection, $event, $entityManager, false);
            $service->ingest($connection, $event, $entityManager, false);

            self::assertSame('historical', $order->getStatus());
            self::assertSame(['DISCONTINUED-SKU'], $order->getUnresolvedSkus());
            self::assertSame('2.0000', $order->getOutstandingQuantity());
            self::assertCount(2, $order->getItems());
            self::assertCount(0, $order->getItems()->first()->getAllocations());

            $entityManager->clear();
            $reloaded = $entityManager->find(SalesOrder::class, $order->getId());
            self::assertInstanceOf(SalesOrder::class, $reloaded);
            self::assertSame('completed', $reloaded->getSourceStatus());
            self::assertSame(['DISCONTINUED-SKU'], $reloaded->getUnresolvedSkus());
            self::assertCount(2, $reloaded->getItems());
            $lineTypes = array_map(
                static fn ($item): string => $item->getLineType(),
                $reloaded->getItems()->toArray(),
            );
            self::assertContains('promotion', $lineTypes);
        } finally {
            $database->rollBack();
        }
    }
}
