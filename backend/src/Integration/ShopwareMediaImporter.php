<?php

namespace App\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationEntityMapping;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSecret;
use App\Entity\Media;
use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Uid\Uuid;

final class ShopwareMediaImporter
{
    private const BATCH_SIZE = 25;

    /** @var array<string, string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
        'application/pdf' => 'pdf',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SecretCipher $cipher,
        private readonly ShopwareClient $shopwareClient,
        private readonly HttpClientInterface $httpClient,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDirectory,
    ) {
    }

    /**
     * @param callable(string): void $onStage
     * @param callable(string, ?string): void $onFailure
     * @param callable(): void $ensureActive
     */
    public function import(
        IntegrationImportRun $run,
        callable $onStage,
        callable $onFailure,
        callable $ensureActive,
    ): void {
        $ensureActive();
        $connection = $run->getConnection();
        $tenant = $run->getTenant();
        $baseUrl = $connection->getConfiguration()['baseUrl'] ?? null;
        if (!is_string($baseUrl) || trim($baseUrl) === '') {
            throw new \RuntimeException('The Shopware platform URL is not configured.');
        }

        $onStage('media');
        $secrets = $this->secrets($connection);
        $referencedMediaIds = $this->referencedMediaIds(
            $baseUrl,
            $secrets,
            $ensureActive,
        );
        $connectionId = $connection->getId();
        $tenantId = $tenant->getId();
        $processed = 0;

        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'media',
            function (int $total, array $items) use (
                &$connection,
                &$tenant,
                &$processed,
                $connectionId,
                $tenantId,
                $baseUrl,
                $referencedMediaIds,
                $onFailure,
                $ensureActive,
            ): void {
                foreach ($items as $sourceMedia) {
                    $ensureActive();
                    $externalId = $this->sourceId($sourceMedia);
                    if (
                        $externalId === null
                        || !isset($referencedMediaIds[$externalId])
                    ) {
                        continue;
                    }

                    try {
                        $this->upsertMedia(
                            $sourceMedia,
                            $connection,
                            $tenant,
                            $baseUrl,
                        );
                    } catch (ImportCancelledException $exception) {
                        throw $exception;
                    } catch (\Throwable $exception) {
                        $onFailure(
                            $exception->getMessage(),
                            $this->sourceId($sourceMedia),
                        );
                    }

                    ++$processed;
                    if ($processed % self::BATCH_SIZE !== 0) {
                        continue;
                    }

                    $this->entityManager->flush();
                    $this->entityManager->clear();
                    [$connection, $tenant] = $this->reloadContext(
                        $connectionId,
                        $tenantId,
                    );
                }
            },
        );

        $ensureActive();
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /** @param array<string, mixed> $source */
    private function upsertMedia(
        array $source,
        IntegrationConnection $connection,
        Tenant $tenant,
        string $baseUrl,
    ): void {
        $attributes = $this->attributes($source);
        $externalId = $this->sourceId($source);
        if ($externalId === null) {
            throw new \RuntimeException('A Shopware media item has no identifier.');
        }

        $declaredMimeType = $attributes['mimeType'] ?? null;
        if (
            is_string($declaredMimeType)
            && $declaredMimeType !== ''
            && !isset(self::ALLOWED_MIME_TYPES[strtolower($declaredMimeType)])
        ) {
            return;
        }

        $mapping = $this->entityManager
            ->getRepository(IntegrationEntityMapping::class)
            ->findOneBy([
                'connection' => $connection,
                'entityType' => 'media',
                'externalId' => $externalId,
            ]);
        if ($mapping instanceof IntegrationEntityMapping) {
            $media = $this->entityManager->getRepository(Media::class)->findOneBy([
                'id' => $mapping->getLocalId(),
                'tenant' => $tenant,
            ]);
            if ($media instanceof Media) {
                return;
            }
        }

        $url = $this->mediaUrl($baseUrl, $attributes['url'] ?? null);
        if ($url === null) {
            throw new \RuntimeException('A Shopware media item has no valid image URL.');
        }

        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 30,
            'max_redirects' => 0,
        ]);
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf(
                'The Shopware image download returned HTTP %d.',
                $statusCode,
            ));
        }

        $content = $response->getContent(false);
        if ($content === '' || strlen($content) > 10 * 1024 * 1024) {
            throw new \RuntimeException('The Shopware image is empty or exceeds 10 MB.');
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;
        if ($extension === null) {
            throw new \RuntimeException('The Shopware media item is not a supported image.');
        }

        $fileName = $this->fileName(
            $attributes['fileName'] ?? null,
            $extension,
        );
        $directory = $this->projectDirectory.'/var/media/'.$tenant->getId()->toRfc4122();
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('The media storage directory could not be created.');
        }

        $storedName = Uuid::v7()->toRfc4122().'.'.$extension;
        $path = $directory.'/'.$storedName;
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new \RuntimeException('The Shopware image could not be stored.');
        }

        $media = new Media(
            $tenant,
            $tenant->getId()->toRfc4122().'/'.$storedName,
            $fileName,
            $extension,
            strlen($content),
            $mimeType,
            hash('sha256', $content),
        );
        $this->entityManager->persist($media);
        $this->ensureMapping(
            $tenant,
            $connection,
            'media',
            $externalId,
            $media,
        );
    }

    /** @return array{IntegrationConnection, Tenant} */
    private function reloadContext(Uuid $connectionId, Uuid $tenantId): array
    {
        $connection = $this->entityManager->find(
            IntegrationConnection::class,
            $connectionId,
        );
        $tenant = $this->entityManager->find(Tenant::class, $tenantId);
        if (
            !$connection instanceof IntegrationConnection
            || !$tenant instanceof Tenant
        ) {
            throw new \RuntimeException('The import context could not be reloaded.');
        }

        return [$connection, $tenant];
    }

    /**
     * @param array<string, string> $secrets
     * @param callable(): void $ensureActive
     *
     * @return array<string, true>
     */
    private function referencedMediaIds(
        string $baseUrl,
        array $secrets,
        callable $ensureActive,
    ): array {
        $mediaIds = [];
        foreach ($this->shopwareClient->productMediaByProductId($baseUrl, $secrets) as $items) {
            $ensureActive();
            foreach ($items as $item) {
                $mediaIds[$item['mediaId']] = true;
            }
        }

        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'category',
            function (int $total, array $items) use ($ensureActive, &$mediaIds): void {
                foreach ($items as $source) {
                    $ensureActive();
                    $mediaId = $this->attributes($source)['mediaId'] ?? null;
                    if (is_string($mediaId) && $mediaId !== '') {
                        $mediaIds[$mediaId] = true;
                    }
                }
            },
        );

        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'product-manufacturer',
            function (int $total, array $items) use ($ensureActive, &$mediaIds): void {
                foreach ($items as $source) {
                    $ensureActive();
                    $mediaId = $this->attributes($source)['mediaId'] ?? null;
                    if (is_string($mediaId) && $mediaId !== '') {
                        $mediaIds[$mediaId] = true;
                    }
                }
            },
        );

        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'property-group-option',
            function (int $total, array $items) use ($ensureActive, &$mediaIds): void {
                foreach ($items as $source) {
                    $ensureActive();
                    $mediaId = $this->attributes($source)['mediaId'] ?? null;
                    if (is_string($mediaId) && $mediaId !== '') {
                        $mediaIds[$mediaId] = true;
                    }
                }
            },
        );

        $this->shopwareClient->forEachEntityPage(
            $baseUrl,
            $secrets,
            'product-download',
            function (int $total, array $items) use ($ensureActive, &$mediaIds): void {
                foreach ($items as $source) {
                    $ensureActive();
                    $mediaId = $this->attributes($source)['mediaId'] ?? null;
                    if (is_string($mediaId) && $mediaId !== '') {
                        $mediaIds[$mediaId] = true;
                    }
                }
            },
        );

        return $mediaIds;
    }

    /** @param array<string, mixed> $source */
    private function attributes(array $source): array
    {
        return is_array($source['attributes'] ?? null)
            ? $source['attributes']
            : $source;
    }

    /** @param array<string, mixed> $source */
    private function sourceId(array $source): ?string
    {
        $attributes = $this->attributes($source);
        $id = $source['id'] ?? $attributes['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function mediaUrl(string $baseUrl, mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $url = trim($value);
        if (str_starts_with($url, '/')) {
            return rtrim($baseUrl, '/').$url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!is_string($scheme) || !in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);
        if (
            !is_string($host)
            || !is_string($baseHost)
            || strcasecmp($host, $baseHost) !== 0
        ) {
            return null;
        }

        return $url;
    }

    private function fileName(mixed $value, string $extension): string
    {
        $name = is_string($value) ? trim($value) : '';
        $name = preg_replace('/[\x00-\x1F\\\\\/]+/', '-', basename($name)) ?? '';
        if ($name === '') {
            return 'image.'.$extension;
        }
        if (!str_ends_with(strtolower($name), '.'.$extension)) {
            $name .= '.'.$extension;
        }

        return mb_substr($name, 0, 255);
    }

    private function ensureMapping(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $type,
        string $externalId,
        Media $media,
    ): void {
        $mapping = $this->entityManager
            ->getRepository(IntegrationEntityMapping::class)
            ->findOneBy([
                'connection' => $connection,
                'entityType' => $type,
                'externalId' => $externalId,
            ]);
        if (!$mapping instanceof IntegrationEntityMapping) {
            $this->entityManager->persist(new IntegrationEntityMapping(
                $tenant,
                $connection,
                $type,
                $externalId,
                $media->getId(),
            ));

            return;
        }

        if ($mapping->getLocalId()->toRfc4122() !== $media->getId()->toRfc4122()) {
            $mapping->remap($media->getId());
        }
    }

    /** @return array<string, string> */
    private function secrets(IntegrationConnection $connection): array
    {
        $secrets = [];
        foreach ($this->entityManager
            ->getRepository(IntegrationSecret::class)
            ->findBy(['connection' => $connection]) as $secret) {
            $secrets[$secret->getSecretKey()] = $this->cipher->decrypt(
                $secret->getCiphertext(),
                $secret->getNonce(),
            );
        }

        return $secrets;
    }
}
