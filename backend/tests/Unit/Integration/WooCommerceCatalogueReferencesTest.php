<?php

namespace App\Tests\Unit\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\Tenant;
use App\Integration\SecretCipher;
use App\Integration\WooCommerceCatalogueReferences;
use App\Integration\WooCommerceClient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WooCommerceCatalogueReferencesTest extends TestCase
{
    public function testNativeAttributeCollectionIsFilteredAndPagedBeforeDisplaying(): void
    {
        $records = array_map(static fn (int $id): array => ['id' => $id, 'name' => 'Attribute '.$id], range(1, 30));
        $references = new WooCommerceCatalogueReferences(
            new WooCommerceClient(new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode($records)))),
            new SecretCipher('test'),
            new ArrayAdapter(),
        );
        $connection = new IntegrationConnection(new Tenant('Fixture'), 'woocommerce', 'Fixture', ['channel'], ['baseUrl' => 'https://woo.example.test']);
        $manager = $this->createStub(EntityManagerInterface::class);
        $secrets = ['consumerKey' => 'test', 'consumerSecret' => 'test'];
        $first = $references->page($connection, $manager, 'propertyGroup', 1, '', [], $secrets);
        self::assertCount(25, $first['items']);
        self::assertTrue($first['pagination']['hasMore']);
        $second = $references->page($connection, $manager, 'propertyGroup', 2, '', [], $secrets);
        self::assertCount(5, $second['items']);
        self::assertSame('26', $second['items'][0]['id']);
        self::assertFalse($second['pagination']['hasMore']);
        $selected = $references->page($connection, $manager, 'propertyGroup', 1, '30', ['30'], $secrets);
        self::assertSame([['id' => '30', 'label' => 'Attribute 30']], $selected['items']);
    }
}
