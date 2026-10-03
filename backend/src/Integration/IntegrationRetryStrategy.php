<?php

namespace App\Integration;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\WrappedExceptionsInterface;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Retry\RetryStrategyInterface;

final class IntegrationRetryStrategy implements RetryStrategyInterface
{
    private readonly MultiplierRetryStrategy $backoff;

    public function __construct()
    {
        $this->backoff = new MultiplierRetryStrategy(5, 2000, 2, 60000, 0.2);
    }

    public function isRetryable(Envelope $message, ?\Throwable $throwable = null): bool
    {
        return $this->backoff->isRetryable($message, $throwable);
    }

    public function getWaitingTime(Envelope $message, ?\Throwable $throwable = null): int
    {
        $delay = $this->backoff->getWaitingTime($message, $throwable);
        $exceptions = $throwable instanceof WrappedExceptionsInterface
            ? $throwable->getWrappedExceptions(IntegrationRetryLaterException::class, true)
            : [$throwable];
        foreach ($exceptions as $exception) {
            if ($exception instanceof IntegrationRetryLaterException) {
                $delay = max($delay, min(3600, max(1, $exception->delaySeconds)) * 1000);
            }
        }

        return $delay;
    }
}
