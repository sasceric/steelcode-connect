<?php

namespace App\Tests\Integration;

use App\Controller\Api\SalesPickTaskController;
use App\Entity\IntegrationConnection;
use App\Entity\Product;
use App\Entity\SalesOrder;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Service\SalesPickListService;
use App\Service\SalesPickTaskService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class SalesPickTaskFlowTest extends KernelTestCase
{
    public function testPickingProgressIsDurableVersionedAndDoesNotShipStock(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            [$order, $user, $item, $warehouse] = $this->order($entityManager);
            $picking = new SalesPickTaskService(new SalesPickListService());

            $task = $picking->start($order, $user, $entityManager);
            self::assertSame('open', $task->getStatus());
            self::assertSame($task->getId(), $picking->start($order, $user, $entityManager)->getId());

            $task = $picking->record(
                $order,
                $task->getId(),
                [$item->getId()->toRfc4122() => '1'],
                1,
                false,
                $user,
                $entityManager,
            );
            self::assertSame('in_progress', $task->getStatus());
            self::assertSame('1.0000', $task->getPickedQuantities()[$item->getId()->toRfc4122()]);
            self::assertSame(2, $task->getVersion());
            self::assertSame('2.0000', $item->getReservedQuantity());
            self::assertSame('0.0000', $item->getFulfilledQuantity());

            try {
                $picking->record(
                    $order,
                    $task->getId(),
                    [$item->getId()->toRfc4122() => '2'],
                    1,
                    true,
                    $user,
                    $entityManager,
                );
                self::fail('An outdated pick-task version should be rejected.');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('changed by someone else', $exception->getMessage());
            }

            $item->addAllocation($warehouse, '1.0000');
            $entityManager->flush();
            self::assertTrue($picking->isStale($task));
            try {
                $picking->start($order, $user, $entityManager);
                self::fail('Already picked goods must not be silently discarded.');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('Resolve the picked items', $exception->getMessage());
            }
            self::assertSame('in_progress', $task->getStatus());
        } finally {
            $database->rollBack();
        }
    }

    public function testUnstartedTaskCanBeReplacedAfterTheOrderChanges(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            [$order, $user, $item, $warehouse] = $this->order($entityManager);
            $picking = new SalesPickTaskService(new SalesPickListService());
            $first = $picking->start($order, $user, $entityManager);

            $item->addAllocation($warehouse, '1.0000');
            $entityManager->flush();

            $second = $picking->start($order, $user, $entityManager);
            self::assertNotSame($first->getId()->toRfc4122(), $second->getId()->toRfc4122());
            self::assertSame('stale', $first->getStatus());
            self::assertSame('3.0000', $second->getLines()[0]['quantity']);
            self::assertSame('open', $second->getStatus());
        } finally {
            $database->rollBack();
        }
    }

    public function testCompletedPickCannotBeStartedAgainAfterPartialShipment(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            [$order, $user, $item] = $this->order($entityManager);
            $picking = new SalesPickTaskService(new SalesPickListService());
            $task = $picking->start($order, $user, $entityManager);
            $picking->record(
                $order,
                $task->getId(),
                [$item->getId()->toRfc4122() => '2'],
                1,
                true,
                $user,
                $entityManager,
            );

            $allocation = $item->getAllocations()->first();
            self::assertNotFalse($allocation);
            $shipped = $allocation->splitForShipment('1.0000');
            self::assertNotNull($shipped);
            $item->addSplitAllocation($shipped);
            $item->fulfill('1.0000');
            $order->markPartiallyFulfilled();
            $entityManager->flush();

            self::assertTrue($picking->isStale($task));
            try {
                $picking->start($order, $user, $entityManager);
                self::fail('A completed pick must not be repeated after a partial shipment.');
            } catch (\DomainException $exception) {
                self::assertStringContainsString('completed pick exists', $exception->getMessage());
            }
            self::assertSame('picked', $task->getStatus());
        } finally {
            $database->rollBack();
        }
    }

    public function testApiRejectsExcessivePickedQuantityAndAcceptsAValidSave(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $database = $entityManager->getConnection();
        $database->beginTransaction();

        try {
            [$order, $user, $item] = $this->order($entityManager);
            $tokens = new TokenStorage();
            $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
            $container = new Container();
            $container->set('security.token_storage', $tokens);
            $controller = new SalesPickTaskController();
            $controller->setContainer($container);
            $picking = new SalesPickTaskService(new SalesPickListService());

            $started = $controller->start(
                $order->getId()->toRfc4122(),
                $entityManager,
                $picking,
            );
            self::assertSame(200, $started->getStatusCode());
            $task = json_decode($started->getContent(), true, 512, JSON_THROW_ON_ERROR)['pickTask'];

            $invalid = $controller->record(
                $order->getId()->toRfc4122(),
                $this->pickRequest($order, $task, $item->getId()->toRfc4122(), '3'),
                $entityManager,
                $picking,
            );
            self::assertSame(422, $invalid->getStatusCode());

            $saved = $controller->record(
                $order->getId()->toRfc4122(),
                $this->pickRequest($order, $task, $item->getId()->toRfc4122(), '2'),
                $entityManager,
                $picking,
            );
            self::assertSame(200, $saved->getStatusCode());
            $payload = json_decode($saved->getContent(), true, 512, JSON_THROW_ON_ERROR)['pickTask'];
            self::assertSame('picked', $payload['status']);
            self::assertSame('2.0000', $payload['pickedQuantities'][$item->getId()->toRfc4122()]);
            self::assertSame('2.0000', $item->getReservedQuantity());
        } finally {
            $database->rollBack();
        }
    }

    private function pickRequest(
        SalesOrder $order,
        array $task,
        string $itemId,
        string $quantity,
    ): Request {
        return Request::create(
            '/api/v1/sales/orders/'.$order->getId()->toRfc4122().'/pick-task',
            'PATCH',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'taskId' => $task['id'],
                'version' => $task['version'],
                'complete' => true,
                'quantities' => [$itemId => $quantity],
            ], JSON_THROW_ON_ERROR),
        );
    }

    /** @return array{SalesOrder, User, \App\Entity\SalesOrderItem, Warehouse} */
    private function order(EntityManagerInterface $entityManager): array
    {
        $tenant = new Tenant('Pick task test '.bin2hex(random_bytes(4)));
        $user = new User('pick-'.bin2hex(random_bytes(4)).'@example.invalid');
        $user->setPassword('test-password-hash');
        $membership = new TenantMembership($tenant, $user, 'owner');
        $connection = new IntegrationConnection($tenant, 'shopware', 'Shop', ['source', 'channel']);
        $product = new Product($tenant);
        $product->updateIdentity('PICK-'.bin2hex(random_bytes(4)), null);
        $warehouse = new Warehouse($tenant, 'MAIN', 'Main warehouse');
        $order = new SalesOrder($tenant, $connection, bin2hex(random_bytes(8)), '10001');
        $item = $order->addItem($product, 'line-1', 'SKU-1', 'Test product', '2.0000');
        $item->addAllocation($warehouse, '2.0000');
        $order->markReserved();

        $entityManager->persist($tenant);
        $entityManager->persist($user);
        $entityManager->persist($membership);
        $entityManager->persist($connection);
        $entityManager->persist($product);
        $entityManager->persist($warehouse);
        $entityManager->persist($order);
        $entityManager->flush();

        return [$order, $user, $item, $warehouse];
    }
}
