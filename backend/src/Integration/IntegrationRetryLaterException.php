<?php

namespace App\Integration;

final class IntegrationRetryLaterException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $delaySeconds = 30,
    )
    {
        parent::__construct($message);
    }
}
