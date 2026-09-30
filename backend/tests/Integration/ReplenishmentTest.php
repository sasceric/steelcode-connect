<?php

namespace App\Tests\Integration;

use App\Controller\Api\ReplenishmentController;
use App\Entity\InventoryLevel;
use App\Entity\Product;
use App\Entity\Supplier;
use App\Entity\SupplierOffer;
use App\Entity\SupplierOfferPrice;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Service\InventoryService;
use App\Service\InventorySyncOutboxService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class ReplenishmentTest extends KernelTestCase
{
    public function testPolicySuggestionAndDraftAreAtomic(): void
    {
        self::bootKernel();
        $entityManager = self::$kernel->getContainer()->get('doctrine')->getManager();
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $tenant = new Tenant('Replenishment test');
            $user = new User('replenishment-'.bin2hex(random_bytes(6)).'@example.test');
            $user->setPassword('not-used');
            $membership = new TenantMembership($tenant, $user, 'owner');
            $warehouse = new Warehouse($tenant, 'default', 'Default warehouse');
            $product = new Product($tenant);
            $supplier = new Supplier($tenant, 'replenishment_supplier', 'Replenishment Supplier');
            $offer = new SupplierOffer($tenant, $supplier, $product);
            $offer->update('R-1', '11.0000', 'EUR', '1.0000', 4, true, true);
            $offer->setPurchaseUnit('case', 12);
            $offer->replacePrices([
                new SupplierOfferPrice($offer, '1.0000', '11.0000', 'EUR', null, null),
            ]);
            foreach ([$tenant, $user, $membership, $warehouse, $product, $supplier, $offer] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $tokens = new TokenStorage();
            $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
            $services = new Container();
            $services->set('security.token_storage', $tokens);
            $controller = new ReplenishmentController(new InventoryService(new InventorySyncOutboxService()));
            $controller->setContainer($services);

            $saved = $controller->savePolicy($this->jsonRequest([
                'warehouseId' => $warehouse->getId()->toRfc4122(),
                'productId' => $product->getId()->toRfc4122(),
                'threshold' => 5,
                'target' => 20,
                'leadDays' => 7,
            ]), $entityManager);
            self::assertSame(200, $saved->getStatusCode(), $saved->getContent());
            $policyId = json_decode($saved->getContent(), true, 512, JSON_THROW_ON_ERROR)['policy']['id'];
            $listing = $controller->index(Request::create('/?suggestions=1'), $entityManager);
            self::assertSame(200, $listing->getStatusCode(), $listing->getContent());
            $items = json_decode($listing->getContent(), true, 512, JSON_THROW_ON_ERROR)['items'];
            self::assertCount(1, $items);
            self::assertSame(2, $items[0]['suggestedPurchaseQuantity']);
            self::assertSame(22, $items[0]['estimatedCost']);

            $created = $controller->createDrafts($this->jsonRequest(['policyIds' => [$policyId]]), $entityManager);
            self::assertSame(201, $created->getStatusCode(), $created->getContent());
            self::assertCount(1, json_decode($created->getContent(), true, 512, JSON_THROW_ON_ERROR)['orders']);
            $duplicate = $controller->createDrafts($this->jsonRequest(['policyIds' => [$policyId]]), $entityManager);
            self::assertSame(409, $duplicate->getStatusCode());

            $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
                'tenant' => $tenant,
                'warehouse' => $warehouse,
                'product' => $product,
            ]);
            self::assertInstanceOf(InventoryLevel::class, $level);
            self::assertSame('20.0000', $level->getReorderTarget());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }

    private function jsonRequest(array $data): Request
    {
        return Request::create(
            '/',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data, JSON_THROW_ON_ERROR),
        );
    }
}
