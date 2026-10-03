<?php

namespace App\Integration;

use App\Entity\IntegrationConnection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/** Pure, read-only planning of reference creation; only the publisher writes these payloads. */
final class ShopwareCatalogueDependencies
{
    public const ENTITIES = [
        'category' => 'category',
        'manufacturer' => 'product-manufacturer',
        'property' => 'property-group-option',
        'propertyGroup' => 'property-group',
        'tax' => 'tax',
        'unit' => 'unit',
        'deliveryTime' => 'delivery-time',
        'customField' => 'custom-field',
        'customFieldSet' => 'custom-field-set',
    ];

    public static function id(IntegrationConnection $connection, string $type, string $localId): string
    {
        return str_replace('-', '', Uuid::v5(
            Uuid::fromString(Uuid::NAMESPACE_URL),
            'connect:'.$connection->getTenant()->getId().':'.$connection->getId().':'.$type.':'.$localId,
        )->toRfc4122());
    }

    public function plan(
        string $type,
        string $localId,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        array $settings,
        array &$dependencies,
        array $path = [],
    ): string
    {
        $key = $type.':'.$localId;
        if (isset($path[$key]) || count($path) > 100) {
            throw new \DomainException('A catalogue reference contains a cyclic hierarchy.');
        }
        $path[$key] = true;
        $external = $this->mapped($type, $localId, $connection, $manager, $settings);
        $id = self::id($connection, $type, $localId);
        if ($external !== null && $external !== $id) {
            return $external;
        }
        if (isset($dependencies[$key])) {
            return $id;
        }
        $table = match ($type) {
            'propertyGroup' => 'property_groups',
            'customFieldSet' => 'custom_field_sets',
            default => CatalogueExportReferences::TABLES[$type] ?? throw new \DomainException('Unsupported reference creation.'),
        };
        $row = $manager->getConnection()->fetchAssociative(
            "SELECT * FROM $table WHERE id = :id AND tenant_id = :tenant",
            ['id' => $localId, 'tenant' => (string) $connection->getTenant()->getId()],
        );
        if (!$row) {
            throw new \DomainException('The catalogue dependency belongs to another tenant or no longer exists.');
        }
        $payload = ['id' => $id];
        $requires = [];
        $child = function (string $childType, string $childId) use ($connection, $manager, $settings, &$dependencies, &$requires, $path): string {
            $value = $this->plan($childType, $childId, $connection, $manager, $settings, $dependencies, $path);
            $requires[$childType][$value] = $value;

            return $value;
        };
        switch ($type) {
            case 'category':
            case 'manufacturer':
                $translations = $manager->getConnection()->fetchAllAssociative(
                    "SELECT t.*, l.code FROM {$type}_translations t JOIN locales l ON l.id = t.locale_id WHERE t.{$type}_id = :id ORDER BY t.locale_id",
                    ['id' => $localId],
                );
                if ($translations === []) {
                    throw new \DomainException('A translated name is required for '.$type.'.');
                }
                $payload['name'] = $translations[0]['name'];
                foreach ($translations as $translation) {
                    if ($translation['code'] === $connection->getTenant()->getDefaultSnippetLocale()) {
                        $payload['name'] = $translation['name'];
                    }
                    $language = $this->mapped('locale', $translation['locale_id'], $connection, $manager, $settings);
                    if ($language === null) {
                        throw new \DomainException('Map every reference translation to an existing destination language: '.$translation['code']);
                    }
                    $payload['translations'][$language] = [
                        'name' => $translation['name'],
                        'description' => $translation['description'],
                    ];
                    if ($type === 'category') {
                        $payload['translations'][$language]['metaTitle'] = $translation['meta_title'];
                        $payload['translations'][$language]['metaDescription'] = $translation['meta_description'];
                        $payload['translations'][$language]['keywords'] = $translation['meta_keywords'];
                    }
                    $requires['locale'][$language] = $language;
                }
                if ($type === 'category') {
                    $payload['type'] = 'page';
                    $payload['active'] = (bool) $row['active'];
                    $payload['visible'] = (bool) $row['visible'];
                    $payload['parentId'] = $row['parent_id'] !== null
                        ? $child('category', $row['parent_id'])
                        : ($settings['categoryRootId'] ?: null);
                    if ($payload['parentId'] !== null) {
                        $requires['category'][$payload['parentId']] = $payload['parentId'];
                    }
                } else {
                    $payload['link'] = $row['website'] ?? null;
                }
                break;
            case 'property':
                $payload['groupId'] = $child('propertyGroup', $row['property_group_id']);
                $payload['name'] = $row['name'];
                $payload['position'] = (int) $row['position'];
                $payload['colorHexCode'] = $row['color_hex'];
                break;
            case 'propertyGroup':
                $payload += [
                    'name' => $row['name'],
                    'displayType' => in_array($row['display_type'], ['text', 'color', 'media'], true) ? $row['display_type'] : 'text',
                    'sortingType' => $row['sorting'] === 'alphanumeric' ? 'alphanumeric' : 'position',
                    'filterable' => (bool) $row['is_filterable'],
                    'visibleOnProductDetailPage' => (bool) $row['display_on_product_detail'],
                    'position' => (int) $row['position'],
                ];
                break;
            case 'tax':
                $payload += ['name' => $row['name'], 'taxRate' => (float) $row['rate']];
                break;
            case 'unit':
            case 'deliveryTime':
                $labels = json_decode($row['labels'], true, flags: JSON_THROW_ON_ERROR);
                $translationTable = $type === 'unit' ? 'unit_translations' : 'delivery_time_translations';
                $translationColumn = $type === 'unit' ? 'unit_id' : 'delivery_time_id';
                foreach ($manager->getConnection()->fetchAllAssociative(
                    "SELECT t.name, l.code FROM $translationTable t JOIN locales l ON l.id = t.locale_id WHERE t.$translationColumn = :id",
                    ['id' => $localId],
                ) as $translation) {
                    $labels[$translation['code']] = $translation['name'];
                }
                $payload['name'] = $labels[$connection->getTenant()->getDefaultSnippetLocale()] ?? reset($labels) ?: $localId;
                foreach ($labels as $code => $label) {
                    $localeId = $manager->getConnection()->fetchOne('SELECT id FROM locales WHERE code = :code', ['code' => $code]);
                    $language = $localeId ? $this->mapped('locale', $localeId, $connection, $manager, $settings) : null;
                    if ($language !== null) {
                        $payload['translations'][$language] = ['name' => $label];
                        $requires['locale'][$language] = $language;
                    }
                }
                if ($type === 'unit') {
                    $payload['shortCode'] = $row['symbol'] ?: $row['code'];
                } else {
                    $payload += ['min' => (int) $row['min'], 'max' => (int) $row['max'], 'unit' => $row['unit']];
                }
                break;
            case 'customFieldSet':
                // Names are namespaced per connection, avoiding unrelated destination sets.
                $payload += [
                    'name' => 'connect_'.substr($id, 0, 12).'_'.$row['technical_name'],
                    'config' => ['label' => json_decode($row['labels'], true, flags: JSON_THROW_ON_ERROR)],
                    'active' => true,
                    'relations' => [[
                        'id' => self::id($connection, 'customFieldSetRelation', $localId),
                        'entityName' => 'product',
                    ]],
                ];
                break;
            case 'customField':
                if (!in_array($row['type'], ['text', 'html', 'datetime', 'int', 'float', 'bool', 'json'], true)) {
                    throw new \DomainException('Unsupported custom field type: '.$row['type']);
                }
                if ($row['custom_field_set_id'] === null) {
                    throw new \DomainException('Assign the custom field to a set before publishing.');
                }
                $payload += [
                    'name' => $row['technical_name'],
                    'type' => $row['type'],
                    'active' => true,
                    'customFieldSetId' => $child('customFieldSet', $row['custom_field_set_id']),
                    'config' => [
                        'label' => json_decode($row['labels'], true, flags: JSON_THROW_ON_ERROR),
                        'customFieldPosition' => (int) $row['position'],
                        'componentName' => 'sw-field',
                        'customFieldType' => $row['type'],
                    ],
                ];
                break;
        }
        $dependencies[$key] = [
            'type' => $type,
            'entity' => self::ENTITIES[$type],
            'localId' => $localId,
            'payload' => $payload,
            'requires' => $requires,
        ];

        return $id;
    }

    private function mapped(
        string $type,
        string $localId,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        array $settings,
    ): ?string
    {
        if (isset($settings['mappings'][$type][$localId])) {
            return $settings['mappings'][$type][$localId];
        }
        $entityType = match ($type) {
            'deliveryTime' => 'delivery_time',
            'customField' => 'custom_field',
            'customFieldSet' => 'custom_field_set',
            'propertyGroup' => 'property_group',
            default => $type,
        };
        $ids = $manager->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT external_id FROM integration_entity_mappings WHERE tenant_id = :tenant AND connection_id = :connection AND entity_type = :type AND local_id = :local',
            ['tenant' => (string) $connection->getTenant()->getId(), 'connection' => (string) $connection->getId(), 'type' => $entityType, 'local' => $localId],
        );

        return count($ids) === 1 && preg_match('/^[a-f0-9]{32}$/i', $ids[0]) ? strtolower($ids[0]) : null;
    }
}
