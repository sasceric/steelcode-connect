<?php

namespace App\Tests\Unit\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\Media;
use App\Entity\Tenant;
use App\Integration\CatalogueMediaDelivery;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CatalogueMediaDeliveryTest extends TestCase
{
    public function testSignedCapabilityIsScopedToAnImmutableTenantMediaSnapshot(): void
    {
        $tenant = new Tenant('Media fixture');
        $connection = new IntegrationConnection($tenant, 'woocommerce', 'Fixture', ['channel'], []);
        $media = new Media($tenant, 'fixture.png', 'fixture.png', 'png', 100, 'image/png', str_repeat('a', 64));
        $delivery = new CatalogueMediaDelivery('test-secret', 'https://connect.example.test');
        parse_str(parse_url($delivery->url($connection, $media), PHP_URL_QUERY), $query);
        $claims = $delivery->verify($query['capability']);
        self::assertSame((string) $tenant->getId(), $claims['tenant']);
        self::assertSame((string) $connection->getId(), $claims['connection']);
        self::assertSame((string) $media->getId(), $claims['media']);
        self::assertSame(str_repeat('a', 64), $claims['checksum']);
        self::assertLessThanOrEqual(time() + 900, $claims['expires']);
        $this->expectException(\DomainException::class);
        $delivery->verify($query['capability'].'tampered');
    }

    public function testCrossTenantMediaCannotReceiveACapability(): void
    {
        $connection = new IntegrationConnection(new Tenant('A'), 'woocommerce', 'Fixture', ['channel'], []);
        $media = new Media(new Tenant('B'), 'fixture.png', 'fixture.png', 'png', 100, 'image/png', str_repeat('b', 64));
        $this->expectException(\DomainException::class);
        (new CatalogueMediaDelivery('test-secret', 'https://connect.example.test'))->url($connection, $media);
    }

    public function testExpiredCorrectlySignedCapabilityIsRejected(): void
    {
        $encoded = rtrim(strtr(base64_encode(json_encode(['expires' => time() - 1])), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', 'catalogue-media:'.$encoded, 'test-secret');
        $this->expectException(\DomainException::class);
        (new CatalogueMediaDelivery('test-secret', 'https://connect.example.test'))->verify($encoded.'.'.$signature);
    }

    public static function unsafeProductionUrls(): iterable
    {
        yield 'HTTP' => ['http://connect.example.com'];
        yield 'loopback' => ['https://127.0.0.1'];
        yield 'private IPv4' => ['https://192.168.1.2'];
        yield 'private IPv6' => ['https://[fd00::1]'];
        yield 'test name' => ['https://connect.example.test'];
        yield 'local name' => ['https://connect.local'];
        yield 'absolute local name' => ['https://connect.local.'];
        yield 'credentials' => ['https://user:password@connect.example.com'];
        yield 'query' => ['https://connect.example.com?secret=foo'];
        yield 'fragment' => ['https://connect.example.com#fragment'];
    }

    #[DataProvider('unsafeProductionUrls')]
    public function testProductionRejectsNonPublicOrAmbiguousMediaUrls(string $url): void
    {
        $tenant = new Tenant('Media production fixture');
        $connection = new IntegrationConnection($tenant, 'woocommerce', 'Fixture', ['channel'], []);
        $media = new Media($tenant, 'fixture.png', 'fixture.png', 'png', 100, 'image/png', str_repeat('a', 64));
        $this->expectException(\DomainException::class);
        (new CatalogueMediaDelivery('test-secret', $url, 'prod'))->url($connection, $media);
    }

    public function testProductionAllowsPublicHttpsAndDevelopmentKeepsLocalTesting(): void
    {
        $tenant = new Tenant('Media public fixture');
        $connection = new IntegrationConnection($tenant, 'woocommerce', 'Fixture', ['channel'], []);
        $media = new Media($tenant, 'fixture.png', 'fixture.png', 'png', 100, 'image/png', str_repeat('a', 64));
        self::assertStringStartsWith('https://connect.example.com/api/', (new CatalogueMediaDelivery(
            'test-secret', 'https://connect.example.com', 'prod',
        ))->url($connection, $media));
        self::assertStringStartsWith('http://127.0.0.1:8000/api/', (new CatalogueMediaDelivery(
            'test-secret', 'http://127.0.0.1:8000', 'dev',
        ))->url($connection, $media));
    }
}
