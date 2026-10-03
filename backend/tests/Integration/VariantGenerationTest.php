<?php

namespace App\Tests\Integration;

use App\Controller\Api\ProductController;
use App\Entity\Locale;
use App\Entity\Product;
use App\Entity\ProductTranslation;
use App\Entity\ProductVariantOptionGroup;
use App\Entity\ProductVariantOptionGroupProperty;
use App\Entity\ProductVariantOptionValue;
use App\Entity\Property;
use App\Entity\PropertyGroup;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Service\ProductVariantConfigurationReader;
use App\Service\SeoUrlService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class VariantGenerationTest extends KernelTestCase
{
    public function testImportedOptionsAreSelectedAndGenerationOnlyAddsMissingCombinations(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $tenant = new Tenant('Variant generator regression');
            $otherTenant = new Tenant('Foreign variant tenant');
            $group = new PropertyGroup($tenant, 'Size', 'size', 'text', true, true, 'position', 0);
            $foreignGroup = new PropertyGroup($otherTenant, 'Size', 'size', 'text', true, true, 'position', 0);
            $foreignProperty = new Property($otherTenant, $foreignGroup, 'Foreign', 'foreign', null, 0);
            $parent = new Product($tenant);
            $parent->updateIdentity('GENERATOR', null);
            $occupied = new Product($tenant);
            $occupied->updateIdentity('GENERATOR.1', null);
            $user = new User('variants-'.bin2hex(random_bytes(6)).'@example.test');
            $user->setPassword('not-used');
            $membership = new TenantMembership($tenant, $user, 'owner');
            $locale = $manager->getRepository(Locale::class)->findOneBy(['code' => 'en-GB']);
            $locale ??= new Locale('en-GB', 'English', 'English');
            foreach ([$tenant, $otherTenant, $group, $foreignGroup, $foreignProperty, $parent, $occupied, $user, $membership, $locale] as $entity) {
                $manager->persist($entity);
            }
            $manager->persist(new ProductTranslation($parent, $locale, 'Variant generator'));
            $properties = [];
            for ($index = 1; $index <= 30; $index++) {
                $property = new Property($tenant, $group, 'Size '.$index, 'size-'.$index, null, $index);
                $properties[] = $property;
                $manager->persist($property);
            }
            $imported = new Product($tenant);
            $imported->makeChildOf($parent, 'IMPORTED', '123456789', [
                (string) $group->getId() => (string) $properties[29]->getId(),
            ]);
            $imported->updatePrices(['EUR' => 12345], [], []);
            $local = new Product($tenant);
            $local->makeChildOf($parent, 'LOCAL', null, ['Size' => 'Size 26']);
            $outsideSelection = new Product($tenant);
            $outsideSelection->makeChildOf($parent, 'KEEP-OUTSIDE', null, [
                'Size' => 'Size 29',
            ]);
            foreach ([$imported, $local, $outsideSelection] as $variant) {
                $manager->persist($variant);
            }
            $manager->persist(new ProductVariantOptionValue($tenant, $local, $properties[25]));
            $configured = new ProductVariantOptionGroup($tenant, $parent, $group, 0);
            $manager->persist($configured);
            $manager->persist(new ProductVariantOptionGroupProperty($tenant, $configured, $properties[0]));
            $manager->flush();
            $tokens = new TokenStorage();
            $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
            $services = new Container();
            $services->set('security.token_storage', $tokens);
            $controller = new ProductController($manager, self::getContainer()->get(SeoUrlService::class));
            $controller->setContainer($services);
            $reader = self::getContainer()->get(ProductVariantConfigurationReader::class);
            $translator = self::getContainer()->get('translator');
            $configuration = $this->payload($controller->variantOptions((string) $parent->getId(), $manager, $reader));
            self::assertCount(3, $configuration['existingCombinations']);
            self::assertCount(4, $configuration['properties']);
            self::assertCount(4, $configuration['optionGroups'][0]['propertyIds']);
            self::assertSame([(string) $properties[29]->getId()], $configuration['existingCombinations'][0]['propertyIds']);
            self::assertSame([(string) $properties[25]->getId()], $configuration['existingCombinations'][1]['propertyIds']);
            self::assertSame([(string) $properties[28]->getId()], $configuration['existingCombinations'][2]['propertyIds']);

            $request = Request::create('/', 'PUT', content: json_encode([
                'optionGroups' => [[
                    'propertyGroupId' => (string) $group->getId(),
                    'propertyIds' => [
                        (string) $properties[0]->getId(),
                        (string) $properties[25]->getId(),
                        (string) $properties[29]->getId(),
                        (string) $properties[29]->getId(),
                    ],
                ]],
            ], JSON_THROW_ON_ERROR));
            self::assertSame(200, $controller->updateVariantOptions(
                (string) $parent->getId(), $request, $manager, $translator,
            )->getStatusCode());
            $result = $this->payload($controller->generateVariants(
                (string) $parent->getId(), $manager, $translator, $reader,
            ));
            self::assertSame(1, $result['created']);
            self::assertSame(2, $result['skipped']);
            self::assertSame(4, $manager->getRepository(Product::class)->count(['parent' => $parent, 'tenant' => $tenant]));
            self::assertSame(['EUR' => 12345], $imported->getPrice());
            self::assertSame('123456789', $imported->getEan());
            self::assertSame($outsideSelection, $manager->getRepository(Product::class)->find($outsideSelection->getId()));
            $new = $manager->getRepository(Product::class)->findOneBy(['tenant' => $tenant, 'sku' => 'GENERATOR.2']);
            self::assertInstanceOf(Product::class, $new);
            self::assertSame($parent, $new->getParent());
            $repeated = $this->payload($controller->generateVariants(
                (string) $parent->getId(), $manager, $translator, $reader,
            ));
            self::assertSame(0, $repeated['created']);
            self::assertSame(3, $repeated['skipped']);
            self::assertSame(4, $manager->getRepository(Product::class)->count(['parent' => $parent, 'tenant' => $tenant]));

            $invalid = Request::create('/', 'PUT', content: json_encode([
                'optionGroups' => [[
                    'propertyGroupId' => (string) $group->getId(),
                    'propertyIds' => [(string) $foreignProperty->getId()],
                ]],
            ], JSON_THROW_ON_ERROR));
            self::assertSame(422, $controller->updateVariantOptions(
                (string) $parent->getId(), $invalid, $manager, $translator,
            )->getStatusCode());
            self::assertSame(1, $manager->getRepository(ProductVariantOptionGroup::class)->count(['product' => $parent]));
            $savedGroup = $manager->getRepository(ProductVariantOptionGroup::class)->findOneBy(['product' => $parent]);
            self::assertSame(3, $manager->getRepository(ProductVariantOptionGroupProperty::class)->count([
                'optionGroup' => $savedGroup,
            ]));
            $wildcard = new Product($tenant);
            $wildcard->makeChildOf($parent, 'ANY-SIZE', null, [(string) $group->getId() => '*']);
            $manager->persist($wildcard);
            $manager->flush();
            $wildcardSelection = Request::create('/', 'PUT', content: json_encode([
                'optionGroups' => [[
                    'propertyGroupId' => (string) $group->getId(),
                    'propertyIds' => [(string) $properties[26]->getId()],
                ]],
            ], JSON_THROW_ON_ERROR));
            self::assertSame(200, $controller->updateVariantOptions(
                (string) $parent->getId(), $wildcardSelection, $manager, $translator,
            )->getStatusCode());
            $covered = $this->payload($controller->generateVariants(
                (string) $parent->getId(), $manager, $translator, $reader,
            ));
            self::assertSame(0, $covered['created']);
            self::assertSame(1, $covered['skipped']);
            self::assertSame(5, $manager->getRepository(Product::class)->count(['parent' => $parent, 'tenant' => $tenant]));
        } finally {
            $database->rollBack();
            $manager->clear();
        }
    }

    private function payload(JsonResponse $response): array
    {
        return json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
