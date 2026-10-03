<?php

namespace App\Tests\Unit\Integration;

use App\Integration\IntegrationRetryLaterException;
use App\Integration\IntegrationRetryStrategy;
use App\Message\QueueShopwareCatalogueSync;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class IntegrationRetryStrategyTest extends TestCase
{
    public function testProviderDelayIsRespectedWithoutUnlimitedRetries(): void
    {
        $strategy = new IntegrationRetryStrategy();
        $envelope = new Envelope(new QueueShopwareCatalogueSync());
        $exception = new HandlerFailedException($envelope, [
            new IntegrationRetryLaterException('Provider rate limit.', 120),
        ]);
        self::assertTrue($strategy->isRetryable($envelope, $exception));
        self::assertSame(120000, $strategy->getWaitingTime($envelope, $exception));
        self::assertFalse($strategy->isRetryable($envelope->with(new RedeliveryStamp(5)), $exception));
        self::assertSame(3600000, $strategy->getWaitingTime(
            $envelope,
            new IntegrationRetryLaterException('Excessive header.', 7200),
        ));
    }

    public function testOrdinaryErrorsKeepFiniteExponentialBackoffWithJitter(): void
    {
        $strategy = new IntegrationRetryStrategy();
        $envelope = new Envelope(new QueueShopwareCatalogueSync());
        $delay = $strategy->getWaitingTime($envelope, new \RuntimeException('Temporary error.'));
        self::assertGreaterThanOrEqual(1600, $delay);
        self::assertLessThanOrEqual(2400, $delay);
    }
}
