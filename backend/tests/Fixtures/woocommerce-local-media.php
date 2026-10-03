<?php

/**
 * Disposable local acceptance fixture only; NEVER install in production.
 * WordPress normally rejects private hosts/nonstandard ports during sideload.
 * Permit only the Connect capability-URL endpoint on the exact local API port.
 */
function steelcode_local_media_fixture_url(string $url): bool
{
    $parts = parse_url($url);

    return is_array($parts)
        && ($parts['scheme'] ?? '') === 'http'
        && ($parts['host'] ?? '') === 'localhost'
        && ($parts['port'] ?? 0) === 8001
        && preg_match('#^/api/v1/catalogue-media/[a-f0-9-]{36}/[^/]+\.(png|jpg|jpeg|webp|gif|avif)$#', $parts['path'] ?? '')
        && preg_match('/^capability=[A-Za-z0-9_-]+\.[a-f0-9]{64}$/', $parts['query'] ?? '');
}

add_filter('http_request_host_is_external', function (bool $allowed, string $host, string $url): bool {
    return $allowed || steelcode_local_media_fixture_url($url);
}, 10, 3);

add_filter('http_allowed_safe_ports', function (array $ports, string $host, string $url): array {
    return steelcode_local_media_fixture_url($url) ? array_unique([...$ports, 8001]) : $ports;
}, 10, 3);
