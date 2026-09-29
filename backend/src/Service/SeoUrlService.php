<?php

namespace App\Service;

use App\Entity\IntegrationSalesChannel;
use App\Entity\Locale;
use App\Entity\SeoUrl;
use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;

final class SeoUrlService
{
    public const ENTITY_PRODUCT = 'product';
    public const ENTITY_CATEGORY = 'category';
    public const ENTITY_MANUFACTURER = 'manufacturer';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
    ) {
    }

    public function syncCanonical(
        Tenant $tenant,
        string $entityType,
        Uuid $entityId,
        Locale $locale,
        string $name,
        ?string $requestedPath = null,
        ?IntegrationSalesChannel $salesChannel = null,
        string $source = 'generated',
        ?bool $modified = null,
    ): SeoUrl {
        $current = $this->canonical(
            $tenant,
            $entityType,
            $entityId,
            $locale,
            $salesChannel,
        );
        $generatedPath = $this->slug($name, $entityType);
        $normalizedPath = $this->normalizePath($requestedPath);
        if (
            $current instanceof SeoUrl
            && $current->getSource() === 'manual'
            && $source === 'import'
        ) {
            return $current;
        }
        if (
            $current instanceof SeoUrl
            && $source === 'import'
            && $normalizedPath === null
        ) {
            return $current;
        }
        if ($normalizedPath === null && $current instanceof SeoUrl && $current->isModified()) {
            return $current;
        }

        $path = $this->uniquePath(
            $tenant,
            $locale,
            $salesChannel,
            $normalizedPath ?? $generatedPath,
            $current,
        );
        $modified ??= $normalizedPath !== null && $normalizedPath !== $generatedPath;
        $effectiveSource = $modified && $source === 'generated'
            ? 'manual'
            : $source;

        if ($current instanceof SeoUrl && $current->getPath() === $path) {
            $current->updateCanonicalMetadata($modified, $effectiveSource);

            return $current;
        }

        $seoUrl = new SeoUrl(
            $tenant,
            $locale,
            $entityType,
            $entityId,
            $path,
            $modified,
            $effectiveSource,
            $salesChannel,
        );
        if ($current instanceof SeoUrl) {
            $this->releaseCanonicalConstraint($current);
            $current->redirectTo($seoUrl);
        }

        $this->entityManager->persist($seoUrl);

        return $seoUrl;
    }

    /**
     * @return array<string, string>
     */
    public function canonicalPaths(Tenant $tenant, string $entityType, Uuid $entityId): array
    {
        $urls = $this->entityManager->getRepository(SeoUrl::class)->findBy([
            'tenant' => $tenant,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'salesChannel' => null,
            'canonical' => true,
            'active' => true,
        ]);
        $paths = [];
        foreach ($urls as $url) {
            if ($url instanceof SeoUrl) {
                $paths[$url->getLocale()->getCode()] = $url->getPath();
            }
        }

        return $paths;
    }

    public function removeForEntity(Tenant $tenant, string $entityType, Uuid $entityId): void
    {
        $urls = $this->entityManager->getRepository(SeoUrl::class)->findBy([
            'tenant' => $tenant,
            'entityType' => $entityType,
            'entityId' => $entityId,
        ]);

        foreach ($urls as $url) {
            $this->entityManager->remove($url);
        }
    }

    private function canonical(
        Tenant $tenant,
        string $entityType,
        Uuid $entityId,
        Locale $locale,
        ?IntegrationSalesChannel $salesChannel,
    ): ?SeoUrl {
        foreach ($this->knownSeoUrls() as $url) {
            if (
                $url->isCanonical()
                && $url->isActive()
                && $url->getEntityType() === $entityType
                && $url->getTenant()->getId()->equals($tenant->getId())
                && $url->getEntityId()->equals($entityId)
                && $url->getLocale()->getId()->equals($locale->getId())
                && $this->sameSalesChannel($url->getSalesChannel(), $salesChannel)
            ) {
                return $url;
            }
        }

        $url = $this->entityManager->getRepository(SeoUrl::class)->findOneBy([
            'tenant' => $tenant,
            'entityType' => $entityType,
            'entityId' => $entityId,
            'locale' => $locale,
            'salesChannel' => $salesChannel,
            'canonical' => true,
            'active' => true,
        ]);

        return $url instanceof SeoUrl ? $url : null;
    }

    private function uniquePath(
        Tenant $tenant,
        Locale $locale,
        ?IntegrationSalesChannel $salesChannel,
        string $path,
        ?SeoUrl $current,
    ): string {
        $candidate = $path;
        $suffix = 2;

        while (true) {
            foreach ($this->knownSeoUrls() as $pending) {
                if (
                    $pending->isActive()
                    && $pending->getPath() === $candidate
                    && $pending->getTenant()->getId()->equals($tenant->getId())
                    && $pending->getLocale()->getId()->equals($locale->getId())
                    && $this->sameSalesChannel($pending->getSalesChannel(), $salesChannel)
                    && $pending !== $current
                ) {
                    $candidate = $path.'-'.($suffix++);

                    continue 2;
                }
            }

            $existing = $this->entityManager->getRepository(SeoUrl::class)->findOneBy([
                'tenant' => $tenant,
                'locale' => $locale,
                'salesChannel' => $salesChannel,
                'path' => $candidate,
                'active' => true,
            ]);
            if (!$existing instanceof SeoUrl || $existing === $current) {
                return $candidate;
            }

            $candidate = $path.'-'.($suffix++);
        }
    }

    /** @return list<SeoUrl> */
    private function knownSeoUrls(): array
    {
        $unitOfWork = $this->entityManager->getUnitOfWork();
        $urls = [];

        foreach ($unitOfWork->getIdentityMap()[SeoUrl::class] ?? [] as $entity) {
            if ($entity instanceof SeoUrl) {
                $urls[spl_object_id($entity)] = $entity;
            }
        }

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof SeoUrl) {
                $urls[spl_object_id($entity)] = $entity;
            }
        }

        return array_values($urls);
    }

    private function sameSalesChannel(
        ?IntegrationSalesChannel $first,
        ?IntegrationSalesChannel $second,
    ): bool {
        if ($first === null || $second === null) {
            return $first === null && $second === null;
        }

        return $first->getId()->equals($second->getId());
    }

    private function releaseCanonicalConstraint(SeoUrl $url): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE seo_urls
             SET canonical = FALSE,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND canonical = TRUE',
            ['id' => $url->getId()->toRfc4122()],
        );
    }

    private function slug(string $name, string $entityType): string
    {
        $slug = strtolower($this->slugger->slug($name)->toString());

        return $slug !== '' ? $slug : $entityType;
    }

    private function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $parts = parse_url($path);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) {
            return null;
        }

        $normalized = trim((string) ($parts['path'] ?? ''), '/');
        $normalized = preg_replace('#/+#', '/', $normalized) ?? '';

        return $normalized !== '' ? $normalized : null;
    }
}
