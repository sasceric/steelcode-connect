<?php

namespace App\Integration;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AnanasConnectionTester
{
    private const API_URL = 'https://api.ananas.rs';

    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /** @param array<string, string> $secrets */
    public function test(array $secrets): void
    {
        $token = $this->httpClient->request('POST', self::API_URL.'/iam/api/v1/auth/token', [
            'headers' => ['X-API-Key' => $secrets['apiKey'], 'Accept' => 'application/json'],
            'json' => [
                'grantType' => 'CLIENT_CREDENTIALS',
                'clientId' => $secrets['clientId'],
                'clientSecret' => $secrets['clientSecret'],
                'scope' => 'public_api/full_access',
            ],
            'timeout' => 10,
        ])->toArray(false);

        if (!isset($token['access_token']) || !is_string($token['access_token'])) {
            throw new \RuntimeException('Ananas did not return an access token.');
        }
    }
}
