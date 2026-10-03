<?php

namespace App\Tests\Integration;

use App\Controller\Api\InventoryController;
use App\Entity\IntegrationConnection;
use App\Entity\InventoryCount;
use App\Entity\InventoryLevel;
use App\Entity\InventoryTransfer;
use App\Entity\Product;
use App\Entity\PurchaseOrder;
use App\Entity\SalesOrder;
use App\Entity\SalesReturn;
use App\Entity\Supplier;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Service\InventoryService;
use App\Service\InventorySyncOutboxService;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class WarehouseLifecycleTest extends KernelTestCase
{
    public function testDeactivationBlocksBalancesAndOpenDocumentsButAllowsEmptyAndHistoricalWarehouses(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $tenant = new Tenant('Warehouse lifecycle rollback fixture');
            $user = new User('warehouse-'.bin2hex(random_bytes(8)).'@example.invalid');
            $user->setPassword('test-password-hash');
            $warehouse = new Warehouse($tenant, 'qa', 'QA warehouse');
            $otherWarehouse = new Warehouse($tenant, 'other', 'Other warehouse');
            $default = new Warehouse($tenant, 'default', 'Default warehouse');
            $product = new Product($tenant);
            $product->updateIdentity('WAREHOUSE-GUARD', null);
            $supplier = new Supplier($tenant, 'qa', 'QA supplier');
            $connection = new IntegrationConnection($tenant, 'shopware', 'QA shop', ['channel']);
            foreach ([$tenant, $user, new TenantMembership($tenant, $user, 'owner'), $warehouse, $otherWarehouse, $default, $product, $supplier, $connection] as $entity) {
                $manager->persist($entity);
            }
            $manager->flush();
            $inventory = new InventoryService(new InventorySyncOutboxService());
            $tokens = new TokenStorage();
            $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
            $container = new Container();
            $container->set('security.token_storage', $tokens);
            $controller = new InventoryController($inventory);
            $controller->setContainer($container);
            $translator = self::getContainer()->get(TranslatorInterface::class);
            $request = Request::create('/', 'PATCH', content: '{"active":false}');
            $level = new InventoryLevel($tenant, $warehouse, $product);
            $manager->persist($level);
            foreach (['setQuantity', 'setReservedQuantity', 'setUnavailableQuantity', 'setIncomingQuantity'] as $setter) {
                $level->$setter('2.0000');
                $manager->flush();
                $response = $controller->updateWarehouse((string) $warehouse->getId(), $request, $manager, $translator);
                self::assertSame(409, $response->getStatusCode());
                self::assertContains('stock', json_decode($response->getContent(), true)['blockers']);
                self::assertTrue($warehouse->isActive());
                $level->$setter('0.0000');
                $manager->flush();
            }
            $po = new PurchaseOrder($tenant, $supplier, $warehouse, 'EUR', null);
            $po->addItem($product, '1.0000', '1.0000', null);
            $manager->persist($po);
            $count = new InventoryCount($tenant, $warehouse);
            $manager->persist($count);
            $transfer = new InventoryTransfer($tenant, $otherWarehouse, $warehouse);
            $transfer->addItem($product, '1.0000');
            $manager->persist($transfer);
            $order = new SalesOrder($tenant, $connection, 'qa-order', 'QA');
            $item = $order->addItem($product, 'qa-line', 'WAREHOUSE-GUARD', 'QA item', '1.0000');
            $allocation = $item->addAllocation($warehouse, '1.0000');
            $manager->persist($order);
            $return = new SalesReturn($item, $warehouse, Uuid::v7(), '1.0000', 'other', 'unknown', 'quarantine', null, $user);
            $manager->persist($return);
            $manager->flush();
            $response = $controller->updateWarehouse((string) $warehouse->getId(), $request, $manager, $translator);
            self::assertSame(409, $response->getStatusCode());
            $blockers = json_decode($response->getContent(), true)['blockers'];
            foreach (['purchaseOrders', 'counts', 'transfers', 'reservations', 'returns'] as $reason) {
                self::assertContains($reason, $blockers);
            }
            $po->cancel();
            $count->post();
            $transfer->cancel();
            $allocation->release();
            $return->resolve('write_off', 'damaged', $user);
            $manager->flush();
            $response = $controller->updateWarehouse((string) $warehouse->getId(), $request, $manager, $translator);
            self::assertSame(200, $response->getStatusCode());
            self::assertFalse($warehouse->isActive());
            $response = $controller->updateWarehouse((string) $default->getId(), $request, $manager, $translator);
            self::assertSame(200, $response->getStatusCode());
            self::assertTrue($default->isActive());
            try {
                $inventory->lockWarehouse($tenant, $warehouse, $manager);
                self::fail('Inactive warehouse operation must be rejected.');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('inactive', $exception->getMessage());
            }
            try {
                $inventory->warehouseDeactivationBlockers(new Tenant('Foreign'), $warehouse, $manager);
                self::fail('Cross-tenant access must be rejected.');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('tenant', $exception->getMessage());
            }
        } finally {
            $database->rollBack();
        }
    }

    public function testSharedOperationalLockPreventsConcurrentDeactivation(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $database = $manager->getConnection();
        self::assertStringEndsWith('_test', $database->fetchOne('SELECT current_database()'));
        $tenant = new Tenant('Committed warehouse lock acceptance fixture');
        $warehouse = new Warehouse($tenant, 'qa-lock', 'QA lock warehouse');
        $manager->persist($tenant);
        $manager->persist($warehouse);
        $manager->flush();
        $second = DriverManager::getConnection($database->getParams());
        try {
            $database->beginTransaction();
            (new InventoryService(new InventorySyncOutboxService()))->lockWarehouse($tenant, $warehouse, $manager);
            $second->executeStatement("SET lock_timeout = '100ms'");
            try {
                $second->executeStatement(
                    'UPDATE warehouses SET active = FALSE WHERE tenant_id = :tenant AND id = :warehouse',
                    ['tenant' => (string) $tenant->getId(), 'warehouse' => (string) $warehouse->getId()],
                );
                self::fail('Deactivation must wait for the operational transaction.');
            } catch (DriverException $exception) {
                self::assertSame('55P03', $exception->getSQLState());
            }
            $database->rollBack();
            self::assertSame(1, $second->executeStatement(
                'UPDATE warehouses SET active = FALSE WHERE tenant_id = :tenant AND id = :warehouse',
                ['tenant' => (string) $tenant->getId(), 'warehouse' => (string) $warehouse->getId()],
            ));
        } finally {
            if ($database->isTransactionActive()) {
                $database->rollBack();
            }
            $second->close();
            $database->executeStatement('DELETE FROM warehouses WHERE tenant_id = :tenant', ['tenant' => (string) $tenant->getId()]);
            $database->executeStatement('DELETE FROM tenants WHERE id = :tenant', ['tenant' => (string) $tenant->getId()]);
        }
    }
}
