<?php

namespace App\Service;

use App\Entity\Media;
use App\Entity\Tenant;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Shared boundary for authenticated downloads and connector media publication. */
final class TenantMediaStorage
{
    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDirectory,
    )
    {
    }

    public function path(Media $media, Tenant $tenant): string
    {
        if (!$media->getTenant()->getId()->equals($tenant->getId())) {
            throw new \DomainException('The media does not belong to the active tenant.');
        }

        return $this->pathForKey($tenant, $media->getStorageKey());
    }

    public function pathForKey(Tenant $tenant, string $key): string
    {
        $legacy = str_starts_with($key, 'product-media/');
        $relative = $legacy ? substr($key, strlen('product-media/')) : $key;
        $tenantId = $tenant->getId()->toRfc4122();
        $segments = explode('/', $relative);
        if ($segments[0] !== $tenantId || count($segments) < 2
            || str_contains($key, "\0") || str_contains($key, '\\')
            || array_intersect($segments, ['', '.', '..']) !== []) {
            throw new \DomainException('The media key is outside the active tenant storage.');
        }

        $base = realpath($this->projectDirectory.'/var/'.($legacy ? 'product-media' : 'media'));
        $root = $base === false ? false : realpath($base.'/'.$tenantId);
        $path = $root === false ? false : realpath($root.'/'.implode('/', array_slice($segments, 1)));
        // Check both the tenant directory and the file: neither may be a symlink escape.
        if ($base === false || $root !== $base.'/'.$tenantId || $path === false
            || !str_starts_with($path, $root.'/') || !is_file($path)) {
            throw new \DomainException('The media file is not available in tenant storage.');
        }

        return $path;
    }
}
