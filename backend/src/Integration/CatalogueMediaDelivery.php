<?php

namespace App\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\Media;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Short-lived capability URL for a single tenant/connection/media snapshot. */
final class CatalogueMediaDelivery
{
    public function __construct(
        #[Autowire(param: 'kernel.secret')]
        private readonly string $secret,
        #[Autowire(env: 'CATALOGUE_MEDIA_BASE_URL')]
        private readonly string $baseUrl,
        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment = 'dev',
    )
    {
    }

    public function url(IntegrationConnection $connection, Media $media): string
    {
        if (!$connection->getTenant()->getId()->equals($media->getTenant()->getId())) {
            throw new \DomainException('Cross-tenant media delivery is forbidden.');
        }
        $this->validateBaseUrl();
        $claims = [
            'tenant' => (string) $connection->getTenant()->getId(),
            'connection' => (string) $connection->getId(),
            'media' => (string) $media->getId(),
            'checksum' => $media->getChecksum(),
            'expires' => time() + 900,
        ];
        $encoded = rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', 'catalogue-media:'.$encoded, $this->secret);

        return rtrim($this->baseUrl, '/').'/api/v1/catalogue-media/'.$media->getId().'/'.rawurlencode($media->getFileName()).'?capability='.$encoded.'.'.$signature;
    }

    public function verify(string $token): array
    {
        [$encoded, $signature] = array_pad(explode('.', $token, 2), 2, '');
        if (strlen($encoded) > 1500 || !hash_equals(hash_hmac('sha256', 'catalogue-media:'.$encoded, $this->secret), $signature)) {
            throw new \DomainException('Invalid media capability.');
        }
        $claims = json_decode(base64_decode(strtr($encoded, '-_', '+/'), true) ?: '', true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($claims) || (int) ($claims['expires'] ?? 0) <= time() || (int) $claims['expires'] > time() + 900) {
            throw new \DomainException('Expired media capability.');
        }

        return $claims;
    }

    private function validateBaseUrl(): void
    {
        $parts = parse_url($this->baseUrl);
        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL) || !is_array($parts)
            || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \DomainException('Configure a reachable catalogue-media API base URL without credentials, query or fragment.');
        }
        if ($this->environment !== 'prod') {
            return;
        }
        $host = rtrim(strtolower(trim($parts['host'] ?? '', '[]')), '.');
        $isIp = filter_var($host, FILTER_VALIDATE_IP);
        $privateIp = $isIp
            && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        $localName = !$isIp && (!str_contains($host, '.')
            || preg_match('/(?:^|\.)(?:localhost|local|internal|test|invalid)$/', $host));
        if ($parts['scheme'] !== 'https' || $privateIp || $localName) {
            throw new \DomainException('Production catalogue media requires a public HTTPS endpoint reachable by the destination shop.');
        }
        // DNS reachability and TLS must also be verified from the shop's network
        // during deployment; URL validation alone cannot prove public delivery.
    }
}
