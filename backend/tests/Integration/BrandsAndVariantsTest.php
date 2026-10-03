<?php

namespace App\Tests\Integration;

use App\Controller\Api\BrandController;
use App\Controller\Api\ProductController;
use App\Controller\Api\ProductReferenceController;
use App\Entity\Brand;
use App\Entity\BrandTranslation;
use App\Entity\IntegrationConnection;
use App\Entity\InventoryLevel;
use App\Entity\Locale;
use App\Entity\Manufacturer;
use App\Entity\Media;
use App\Entity\Product;
use App\Entity\ProductBrand;
use App\Entity\ProductMedia;
use App\Entity\ProductTranslation;
use App\Entity\Tax;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Integration\WooCommerceCatalogueImporter;
use App\Service\ProductBrandService;
use App\Service\SeoUrlService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class BrandsAndVariantsTest extends KernelTestCase
{
    public function testAllBrandsReconcileSeparatelyFromManufacturerAndRespectTenantBoundaries(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $tenant = new Tenant('Brand regression');
            $otherTenant = new Tenant('Other brand tenant');
            $connection = new IntegrationConnection(
                $tenant,
                'woocommerce',
                'Brands test',
                ['source'],
            );
            $manufacturer = new Manufacturer($tenant);
            $tax = new Tax($tenant, 'Brand test tax', '20.00');
            $user = new User('brands-' . bin2hex(random_bytes(6)) . '@example.test');
            $user->setPassword('not-used');
            $membership = new TenantMembership($tenant, $user, 'owner');
            $locale = $manager->getRepository(Locale::class)->findOneBy(['code' => 'en-GB']);
            $locale ??= new Locale('en-GB', 'English', 'English');
            $manualBrand = new Brand($tenant);
            $foreignBrand = new Brand($otherTenant);
            foreach ([
                $tenant,
                $otherTenant,
                $connection,
                $manufacturer,
                $tax,
                $user,
                $membership,
                $locale,
                $manualBrand,
                $foreignBrand,
            ] as $entity) {
                $manager->persist($entity);
            }
            $manager->persist(new BrandTranslation($manualBrand, $locale, 'Manual brand'));
            $manager->persist(new BrandTranslation($foreignBrand, $locale, 'Foreign brand'));
            $manager->flush();
            $catalogue = self::getContainer()->get(WooCommerceCatalogueImporter::class);
            $brands = self::getContainer()->get(ProductBrandService::class);
            foreach ([['id' => 1, 'name' => 'Brand One'], ['id' => 2, 'name' => 'Brand Two']] as $source) {
                self::assertTrue(
                    $catalogue->reference(
                        'brand',
                        $source,
                        $connection,
                        $manager,
                    ),
                );
                $manager->flush();
                self::assertFalse(
                    $catalogue->reference(
                        'brand',
                        $source,
                        $connection,
                        $manager,
                    ),
                );
                $manager->flush();
            }
            $source = [
                'id' => 10,
                'sku' => 'BRAND-10',
                'name' => 'Branded parent',
                'brands' => [['id' => 1], ['id' => 2], ['id' => 2]],
            ];
            $catalogue->product(
                $source,
                $connection,
                $manager,
                [],
                [],
            );
            $manager->flush();
            $product = $catalogue->mapped(
                $connection,
                'product',
                '10',
                Product::class,
                $manager,
            );
            self::assertCount(2, $brands->brands($product, $manager));
            self::assertNull($product->getManufacturer());
            $product->updateManufacturer($manufacturer);
            $product->updateReferences(
                $tax,
                null,
                null,
                null,
                null,
            );
            $manager->persist(new ProductBrand($product, $manualBrand));
            $manager->flush();
            $catalogue->product(
                $source,
                $connection,
                $manager,
                [],
                [],
            );
            $manager->flush();
            self::assertCount(3, $brands->brands($product, $manager));
            self::assertSame($manufacturer, $product->getManufacturer());
            $variantSource = ['id' => 11, 'sku' => 'BRAND-11', 'name' => 'Branded variant'];
            $catalogue->product(
                $variantSource,
                $connection,
                $manager,
                [],
                [],
                '10',
            );
            $manager->flush();
            $variant = $catalogue->mapped(
                $connection,
                'product',
                '11',
                Product::class,
                $manager,
            );
            self::assertCount(3, $brands->brands($variant, $manager));
            $source['brands'] = [['id' => 2]];
            $catalogue->product(
                $source,
                $connection,
                $manager,
                [],
                ['manufacturers' => false],
            );
            $manager->flush();
            self::assertCount(3, $brands->brands($product, $manager));
            $catalogue->product(
                $source,
                $connection,
                $manager,
                [],
                [],
            );
            $manager->flush();
            self::assertCount(2, $brands->brands($product, $manager));
            self::assertContains($manualBrand, $brands->brands($product, $manager));
            self::assertSame($manufacturer, $product->getManufacturer());
            $services = $this->services($user);
            $references = new ProductReferenceController();
            $references->setContainer($services);
            $requestData = [
                'taxId' => (string) $tax->getId(),
                'manufacturerId' => (string) $manufacturer->getId(),
                'brandIds' => [(string) $manualBrand->getId()],
            ];
            $response = $references->update(
                (string) $product->getId(),
                $this->request($requestData),
                $manager,
                self::getContainer()->get('translator'),
                $brands,
            );
            self::assertSame(200, $response->getStatusCode());
            $payload = json_decode(
                $references->show((string) $product->getId(), $manager, $brands)->getContent(),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            self::assertCount(1, $payload['brands']);
            self::assertSame((string) $manualBrand->getId(), $payload['brands'][0]['id']);
            self::assertSame((string) $manufacturer->getId(), $payload['manufacturerId']);
            foreach ([[(string) $foreignBrand->getId()], [['invalid' => 'object']], ['invalid-id']] as $invalidIds) {
                $requestData['brandIds'] = $invalidIds;
                $response = $references->update(
                    (string) $product->getId(),
                    $this->request($requestData),
                    $manager,
                    self::getContainer()->get('translator'),
                    $brands,
                );
                self::assertSame(422, $response->getStatusCode());
                self::assertCount(1, $brands->brands($product, $manager));
            }
            for ($index = 0; $index < 25; $index++) {
                $brand = new Brand($tenant);
                $manager->persist($brand);
                $manager->persist(new BrandTranslation($brand, $locale, sprintf('Paged brand %02d', $index)));
            }
            $manager->flush();
            $controller = new BrandController();
            $controller->setContainer($services);
            $page = json_decode(
                $controller->index(new Request(['page' => 1, 'limit' => 25]), $manager, $brands)->getContent(),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            self::assertCount(25, $page['brands']);
            self::assertSame(28, $page['pagination']['total']);
            self::assertTrue($page['pagination']['hasMore']);
            $page = json_decode(
                $controller->index(new Request(['page' => 2, 'limit' => 25]), $manager, $brands)->getContent(),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            self::assertCount(3, $page['brands']);
            self::assertFalse($page['pagination']['hasMore']);
            $page = json_decode(
                $controller->index(new Request(['search' => 'Brand One']), $manager, $brands)->getContent(),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            self::assertCount(1, $page['brands']);
            self::assertSame('Brand One', $page['brands'][0]['name']);
            $this->expectException(\DomainException::class);
            new ProductBrand($product, $foreignBrand);
        } finally {
            $database->rollBack();
            $manager->clear();
        }
    }

    public function testVariantPagesUseOwnOrParentCoverAndAvailableStockAcrossWarehouses(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $tenant = new Tenant('Variant columns test');
            $user = new User('variants-' . bin2hex(random_bytes(6)) . '@example.test');
            $user->setPassword('not-used');
            $membership = new TenantMembership($tenant, $user, 'owner');
            $parent = new Product($tenant);
            $firstWarehouse = new Warehouse($tenant, 'first', 'First warehouse');
            $secondWarehouse = new Warehouse($tenant, 'second', 'Second warehouse');
            $locale = $manager->getRepository(Locale::class)->findOneBy(['code' => 'en-GB']);
            $locale ??= new Locale('en-GB', 'English', 'English');
            $media = new Media(
                $tenant,
                'not-a-real-file',
                'cover',
                'webp',
                100,
                'image/webp',
                null,
            );
            $parentCover = new ProductMedia(
                $tenant,
                $parent,
                $media,
                0,
            );
            foreach ([
                $tenant,
                $user,
                $membership,
                $parent,
                $firstWarehouse,
                $secondWarehouse,
                $locale,
                $media,
                $parentCover,
            ] as $entity) {
                $manager->persist($entity);
            }
            $variants = [];
            for ($index = 1; $index <= 3; $index++) {
                $variant = new Product($tenant);
                $variant->makeChildOf(
                    $parent,
                    'VAR-' . $index,
                    null,
                    ['Color' => 'Blue'],
                );
                $variants[] = $variant;
                $manager->persist($variant);
                $manager->persist(new ProductTranslation($variant, $locale, 'Variant ' . $index));
            }
            $variantCover = new ProductMedia(
                $tenant,
                $variants[1],
                $media,
                0,
            );
            $manager->persist($variantCover);
            $firstLevel = new InventoryLevel($tenant, $firstWarehouse, $variants[0]);
            $firstLevel->setQuantity('10.0000');
            $firstLevel->setReservedQuantity('2.0000');
            $firstLevel->setUnavailableQuantity('1.0000');
            $secondLevel = new InventoryLevel($tenant, $secondWarehouse, $variants[0]);
            $secondLevel->setQuantity('4.0000');
            $secondLevel->setReservedQuantity('1.0000');
            $manager->persist($firstLevel);
            $manager->persist($secondLevel);
            $manager->flush();
            $controller = new ProductController($manager, self::getContainer()->get(SeoUrlService::class));
            $controller->setContainer($this->services($user));
            $page = json_decode(
                $controller->variants((string) $parent->getId(), new Request(['limit' => 2, 'sort' => 'sku']), $manager)->getContent(),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            self::assertCount(2, $page['variants']);
            self::assertSame(3, $page['pagination']['total']);
            self::assertTrue($page['pagination']['hasMore']);
            self::assertSame(10, $page['variants'][0]['stock']);
            self::assertSame(
                '/api/v1/products/' . $parent->getId() . '/media/' . $parentCover->getId() . '/file',
                $page['variants'][0]['coverUrl'],
            );
            self::assertSame(
                '/api/v1/products/' . $variants[1]->getId() . '/media/' . $variantCover->getId() . '/file',
                $page['variants'][1]['coverUrl'],
            );
            self::assertSame(0, $page['variants'][1]['stock']);
            $page = json_decode(
                $controller->variants((string) $parent->getId(), new Request(['search' => 'VAR-3']), $manager)->getContent(),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            self::assertCount(1, $page['variants']);
            self::assertSame('VAR-3', $page['variants'][0]['sku']);
        } finally {
            $database->rollBack();
            $manager->clear();
        }
    }

    private function services(User $user): Container
    {
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $services = new Container();
        $services->set('security.token_storage', $tokens);
        return $services;
    }

    private function request(array $payload): Request
    {
        return new Request(content: json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
