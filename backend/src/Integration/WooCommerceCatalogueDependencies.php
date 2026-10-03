<?php

namespace App\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\Media;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class WooCommerceCatalogueDependencies
{
    public function __construct(
        private readonly WooCommerceClient $client,
        private readonly WooCommerceCataloguePayload $builder,
        private readonly CatalogueMediaDelivery $mediaDelivery,
    )
    {
    }

    /** Resolves canonical local tokens; new taxonomy IDs are durable between chunks. */
    public function resolve(
        array $built,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        array $settings,
        string $url,
        array $secrets,
        bool $write,
        float $deadline,
    ): array
    {
        $byToken = [];
        foreach ($built['dependencies'] as $dependency) {
            $byToken[WooCommerceCataloguePayload::token($dependency['type'], $dependency['localId'])] = $dependency;
        }
        $resolved = [];
        $names = [];
        $visiting = [];
        $resolve = function (string $token) use (&$resolve, &$resolved, &$names, &$visiting, $byToken, $connection, $manager, $settings, $url, $secrets, $write, $deadline): int {
            if (array_key_exists($token, $resolved)) {
                return $resolved[$token];
            }
            if (isset($visiting[$token])) {
                throw new \DomainException('A category/reference cycle prevents publication.');
            }
            if (microtime(true) >= $deadline) {
                throw new CatalogueChunkDeadline();
            }
            $visiting[$token] = true;
            $dependency = $byToken[$token] ?? throw new \DomainException('An export reference is missing.');
            $type = $dependency['type'];
            $local = $dependency['localId'];
            $payload = $dependency['payload'];
            if ($type === 'media') {
                $media = $manager->getRepository(Media::class)->findOneBy(['tenant' => $connection->getTenant(), 'id' => $local]);
                if (!$media instanceof Media || $media->getChecksum() !== $payload['checksum']) {
                    throw new \DomainException('The export image changed or is unavailable.');
                }
                $resolved[$token] = 0; // URL is minted only when preparing the actual remote write.
                $names[$token] = $write ? $this->mediaDelivery->url($connection, $media) : '';
                unset($visiting[$token]);

                return 0;
            }
            $external = $this->builder->externalId($connection, $manager, $type, $local, $settings);
            if ($type === 'propertyGroup' && $external === null && $manager->getConnection()->fetchOne(
                "SELECT 1 FROM integration_entity_mappings WHERE tenant_id = :tenant AND connection_id = :connection AND entity_type = 'property_group' AND local_id = :local AND external_id LIKE 'local-%'",
                ['tenant' => (string) $connection->getTenant()->getId(), 'connection' => (string) $connection->getId(), 'local' => $local],
            )) {
                $resolved[$token] = 0;
                $names[$token] = $payload['name'];
                unset($visiting[$token]);

                return 0;
            }
            if ($type === 'property') {
                $groupToken = $payload['group'];
                $group = $resolve($groupToken);
                if ($group === 0) {
                    $resolved[$token] = 0;
                    $names[$token] = $payload['name'];
                    unset($visiting[$token]);

                    return 0;
                }
                $resource = 'products/attributes/'.$group.'/terms';
                if ($external !== null) {
                    if (!str_starts_with($external, $group.':term:')) {
                        throw new \DomainException('The mapped attribute term belongs to another group.');
                    }
                    $external = substr($external, strlen($group.':term:'));
                }
                unset($payload['group']);
            } else {
                $resource = WooCommerceCatalogueReferences::RESOURCES[$type] ?? throw new \DomainException('Unsupported reference resource.');
            }
            if ($type === 'propertyGroup') {
                $payload['slug'] = 'sc_'.substr(hash('sha256', $token.':'.$connection->getId()), 0, 24);
            }
            if (isset($payload['parent']) && is_string($payload['parent'])) {
                $payload['parent'] = $resolve($payload['parent']);
            }
            $record = null;
            if ($external !== null) {
                $record = $this->client->queued('GET', $url, $secrets, $resource.'/'.$external)['data'];
            } elseif (!($settings['createMissingReferences'] ?? false)) {
                throw new \DomainException('Missing '.$type.' mapping. Map it or enable creation of missing references.');
            } elseif ($write) {
                // Terms have unique slugs. Attributes expose all records without
                // pagination, so match only our deterministic, connection-owned slug.
                $query = $type === 'propertyGroup' ? [] : ['slug' => $payload['slug'], 'per_page' => 2];
                $records = $this->client->queued('GET', $url, $secrets, $resource, query: $query)['data'];
                foreach ($records as $candidate) {
                    if (in_array($candidate['slug'] ?? '', [$payload['slug'], 'pa_'.$payload['slug']], true)) {
                        $record = $candidate;
                    }
                }
                if ($record === null) {
                    $record = $this->client->queued('POST', $url, $secrets, $resource, $payload)['data'];
                }
                foreach (['name', 'parent'] as $key) {
                    if (isset($payload[$key]) && ($record[$key] ?? null) != $payload[$key]) {
                        throw new \DomainException('A Connect-created reference was changed. Map it explicitly before publication.');
                    }
                }
                $mappingId = $type === 'property' ? $group.':term:'.$record['id'] : (string) $record['id'];
                $entityType = $type === 'propertyGroup' ? 'property_group' : $type;
                $owner = $manager->getConnection()->fetchAssociative(
                    'SELECT tenant_id, local_id FROM integration_entity_mappings WHERE connection_id = :connection AND entity_type = :type AND external_id = :external',
                    ['connection' => (string) $connection->getId(), 'type' => $entityType, 'external' => $mappingId],
                );
                if ($owner && ($owner['tenant_id'] !== (string) $connection->getTenant()->getId() || $owner['local_id'] !== $local)) {
                    throw new \DomainException('A reference identity is already owned by another local record.');
                }
                $manager->getConnection()->executeStatement(
                    'INSERT INTO integration_entity_mappings (id, tenant_id, connection_id, entity_type, external_id, local_id)
                        VALUES (:id, :tenant, :connection, :type, :external, :local)
                        ON CONFLICT (connection_id, entity_type, external_id) DO NOTHING',
                    ['id' => (string) Uuid::v7(), 'tenant' => (string) $connection->getTenant()->getId(), 'connection' => (string) $connection->getId(), 'type' => $entityType, 'external' => $mappingId, 'local' => $local],
                );
            }
            $resolved[$token] = (int) ($record['id'] ?? 0);
            $names[$token] = $record['name'] ?? $payload['name'];
            unset($visiting[$token]);

            return $resolved[$token];
        };
        foreach (array_keys($byToken) as $token) {
            $resolve($token);
        }
        $replace = function (mixed $value, ?string $key = null) use (&$replace, $resolved, $names): mixed {
            if (is_array($value)) {
                if (isset($value['id']) && is_string($value['id']) && str_starts_with($value['id'], '@media:')) {
                    $token = $value['id'];
                    unset($value['id']);
                    $value['src'] = $names[$token];

                    return $value;
                }
                if (isset($value['id']) && is_string($value['id']) && str_starts_with($value['id'], '@propertyGroup:') && ($resolved[$value['id']] ?? 0) === 0) {
                    $value['name'] = $names[$value['id']];
                }
                foreach ($value as $childKey => $child) {
                    $value[$childKey] = $replace($child, is_string($childKey) ? $childKey : null);
                }

                return $value;
            }
            if (is_string($value) && array_key_exists($value, $resolved)) {
                return $key === 'option' || str_starts_with($value, '@property:') ? $names[$value] : $resolved[$value];
            }

            return $value;
        };

        return $replace($built['payload']);
    }
}
