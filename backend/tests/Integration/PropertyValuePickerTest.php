<?php

namespace App\Tests\Integration;

use App\Controller\Api\ProductController;
use App\Controller\Api\PropertyGroupController;
use App\Entity\Locale;
use App\Entity\Product;
use App\Entity\ProductPropertyAssignment;
use App\Entity\Property;
use App\Entity\PropertyGroup;
use App\Entity\PropertyTranslation;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Service\PropertyValueReader;
use App\Service\SeoUrlService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PropertyValuePickerTest extends KernelTestCase
{
    public function testGroupValuesArePagedSearchableLocalizedAndTenantScoped(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $tenant = new Tenant('Property picker regression');
            $otherTenant = new Tenant('Other picker tenant');
            $group = new PropertyGroup($tenant, 'Color', 'color', 'text', true, true, 'position', 0);
            $foreignGroup = new PropertyGroup($otherTenant, 'Other Color', 'color', 'text', true, true, 'position', 0);
            $product = new Product($tenant);
            $user = new User('property-picker-'.bin2hex(random_bytes(6)).'@example.test');
            $user->setPassword('not-used');
            $membership = new TenantMembership($tenant, $user, 'owner');
            $locale = $manager->getRepository(Locale::class)->findOneBy(['code' => 'de-DE']);
            $locale ??= new Locale('de-DE', 'German', 'Deutsch');
            foreach ([$tenant, $otherTenant, $group, $foreignGroup, $product, $user, $membership, $locale] as $entity) {
                $manager->persist($entity);
            }
            $properties = [];
            for ($index = 1; $index <= 30; $index++) {
                $property = new Property(
                    $tenant,
                    $group,
                    sprintf('Value %02d', $index),
                    'value-'.$index,
                    null,
                    $index,
                );
                $properties[] = $property;
                $manager->persist($property);
            }
            $manager->persist(new PropertyTranslation($properties[29], $locale, 'Blau'));
            $manager->persist(new ProductPropertyAssignment($tenant, $product, $properties[29]));
            $manager->flush();
            $tokens = new TokenStorage();
            $tokens->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
            $services = new Container();
            $services->set('security.token_storage', $tokens);
            $controller = new PropertyGroupController();
            $controller->setContainer($services);
            $reader = self::getContainer()->get(PropertyValueReader::class);
            $groups = json_decode($controller->index(new Request(), $manager)->getContent(), true, flags: JSON_THROW_ON_ERROR);
            self::assertCount(1, $groups['propertyGroups']);
            self::assertSame([], $groups['propertyGroups'][0]['properties']);
            self::assertSame(30, $groups['propertyGroups'][0]['propertyCount']);
            $firstPage = json_decode($controller->values((string) $group->getId(), new Request(), $manager, $reader)->getContent(), true, flags: JSON_THROW_ON_ERROR);
            self::assertCount(25, $firstPage['properties']);
            self::assertSame(30, $firstPage['pagination']['total']);
            self::assertTrue($firstPage['pagination']['hasMore']);
            self::assertSame('Value 01', $firstPage['properties'][0]['name']);
            self::assertSame((string) $group->getId(), $firstPage['properties'][0]['propertyGroupId']);
            $secondPage = json_decode($controller->values((string) $group->getId(), new Request(['page' => 2]), $manager, $reader)->getContent(), true, flags: JSON_THROW_ON_ERROR);
            self::assertCount(5, $secondPage['properties']);
            self::assertFalse($secondPage['pagination']['hasMore']);
            self::assertSame('Value 26', $secondPage['properties'][0]['name']);
            self::assertCount(30, array_unique(array_column([...$firstPage['properties'], ...$secondPage['properties']], 'id')));
            foreach ([['search' => 'Value 30'], ['search' => 'value-30'], ['search' => 'Blau', 'locale' => 'de-DE']] as $query) {
                $result = json_decode($controller->values((string) $group->getId(), new Request($query), $manager, $reader)->getContent(), true, flags: JSON_THROW_ON_ERROR);
                self::assertCount(1, $result['properties']);
                self::assertSame((string) $properties[29]->getId(), $result['properties'][0]['id']);
                self::assertSame('Blau', $result['properties'][0]['labels']['de-DE']);
            }
            $products = new ProductController($manager, self::getContainer()->get(SeoUrlService::class));
            $products->setContainer($services);
            $assigned = json_decode($products->productProperties((string) $product->getId(), $manager, $reader)->getContent(), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame([(string) $properties[29]->getId()], $assigned['propertyIds']);
            self::assertSame('Value 30', $assigned['properties'][0]['name']);
            self::assertSame((string) $group->getId(), $assigned['properties'][0]['propertyGroupId']);
            $this->expectException(NotFoundHttpException::class);
            $controller->values((string) $foreignGroup->getId(), new Request(), $manager, $reader);
        } finally {
            $database->rollBack();
            $manager->clear();
        }
    }
}
