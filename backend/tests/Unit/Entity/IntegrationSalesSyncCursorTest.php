<?php

namespace App\Tests\Unit\Entity;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationSalesSyncCursor;
use App\Entity\Tenant;
use PHPUnit\Framework\TestCase;

final class IntegrationSalesSyncCursorTest extends TestCase
{
    public function testItNeverMovesItsCheckpointBackwards(): void
    {
        $tenant = new Tenant('Cursor test');
        $connection = new IntegrationConnection($tenant, 'shopware', 'Shopware', ['channel']);
        $startedAt = new \DateTimeImmutable('2026-09-29T10:00:00+00:00');
        $cursor = new IntegrationSalesSyncCursor($connection, $startedAt);

        $cursor->advance(new \DateTimeImmutable('2026-09-29T10:02:00+00:00'));
        $cursor->advance(new \DateTimeImmutable('2026-09-29T10:01:00+00:00'));

        self::assertSame('2026-09-29T10:02:00+00:00', $cursor->getLastSyncedAt()->format(\DateTimeInterface::ATOM));

        $cursor->restart(new \DateTimeImmutable('2026-09-29T11:00:00+00:00'));
        self::assertSame('2026-09-29T11:00:00+00:00', $cursor->getLastSyncedAt()->format(\DateTimeInterface::ATOM));
    }

    public function testFailureIsVisibleUntilACompletedSyncAdvancesTheCheckpoint(): void
    {
        $tenant = new Tenant('Cursor failure test');
        $connection = new IntegrationConnection($tenant, 'shopware', 'Shopware', ['channel']);
        $startedAt = new \DateTimeImmutable('2026-09-29T10:00:00+00:00');
        $cursor = new IntegrationSalesSyncCursor($connection, $startedAt);

        $cursor->fail('The Shopware request failed.');

        self::assertSame($startedAt, $cursor->getLastSyncedAt());
        self::assertSame('The Shopware request failed.', $cursor->getLastError());
        self::assertNotNull($cursor->getLastErrorAt());

        $cursor->advance(new \DateTimeImmutable('2026-09-29T10:01:00+00:00'));

        self::assertNull($cursor->getLastError());
        self::assertNull($cursor->getLastErrorAt());
    }
}
