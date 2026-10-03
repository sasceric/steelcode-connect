<?php

namespace App\Integration;

/** A permanent API response; provider credentials/response bodies are not stored here. */
final class ShopwareRequestException extends \RuntimeException
{
    public function __construct(public readonly int $statusCode, string $message)
    {
        parent::__construct($message);
    }
}
