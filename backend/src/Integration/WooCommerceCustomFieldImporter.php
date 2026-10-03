<?php

namespace App\Integration;

use App\Entity\CustomField;
use App\Entity\CustomFieldSet;
use App\Entity\IntegrationConnection;
use Doctrine\ORM\EntityManagerInterface;

final class WooCommerceCustomFieldImporter
{
    /** Stable, typed definitions in one set; source namespaces avoid collisions. */
    public function values(
        IntegrationConnection $connection,
        string $entityType,
        array $metadata,
        EntityManagerInterface $manager,
        array $existing = [],
    ): array
    {
        return $manager->wrapInTransaction(function () use (
            $connection,
            $entityType,
            $metadata,
            $manager,
            $existing,
        ): array {
            // Two Woo connections in one tenant may create the shared set at
            // the same time. Serialize definition updates, including live sync.
            $manager->getConnection()->executeQuery(
                'SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))',
                ['key' => 'woo-custom-fields:' . $connection->getTenant()->getId()],
            );

            return $this->writeValues($connection, $entityType, $metadata, $manager, $existing);
        });
    }

    private function writeValues(
        IntegrationConnection $connection,
        string $entityType,
        array $metadata,
        EntityManagerInterface $manager,
        array $existing,
    ): array
    {
        if (!in_array($entityType, ['product', 'customer', 'order'], true)) {
            throw new \InvalidArgumentException('Unsupported WooCommerce metadata entity.');
        }
        $tenant = $connection->getTenant();
        $prefix = 'woo_' . substr(hash('sha256', (string) $connection->getId()), 0, 12) . '_' . $entityType . '_';
        foreach (array_keys($existing) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($existing[$key]);
            }
        }
        $values = [];
        foreach (SourcePayloadSanitizer::sanitize($metadata) as $item) {
            if (!is_array($item) || !is_string($item['key'] ?? null) || $item['key'] === '') {
                continue;
            }
            $values[$item['key']][] = $item['value'] ?? null;
        }
        if ($values === []) {
            return $existing;
        }
        $set = $manager->getRepository(CustomFieldSet::class)->findOneBy([
            'tenant' => $tenant,
            'technicalName' => 'woocommerce_fields',
        ]);
        if (!$set instanceof CustomFieldSet) {
            $set = new CustomFieldSet(
                $tenant,
                'woocommerce_fields',
                [
                    'en-GB' => 'WooCommerce fields',
                    'de-DE' => 'WooCommerce-Felder',
                    'bs-BA' => 'WooCommerce polja',
                ],
                ['product', 'customer', 'order'],
                0,
            );
            $manager->persist($set);
            $manager->flush();
        } else {
            $set->update(
                $set->getTechnicalName(),
                $set->getLabels(),
                array_values(array_unique([...$set->getRelations(), 'product', 'customer', 'order'])),
                $set->getPosition(),
            );
        }
        foreach ($values as $key => $items) {
            $value = count($items) === 1 ? $items[0] : $items;
            $name = $prefix . substr(hash('sha256', $key), 0, 24);
            $type = match (true) {
                is_bool($value) => 'switch',
                is_int($value), is_float($value) => 'number',
                is_string($value) => 'text',
                default => 'json',
            };
            $field = $manager->getRepository(CustomField::class)->findOneBy([
                'tenant' => $tenant,
                'customFieldSet' => $set,
                'technicalName' => $name,
            ]);
            // Heterogeneous plugin values must stay lossless, not be coerced.
            if ($field instanceof CustomField && $field->getType() !== $type) {
                $type = 'json';
            }
            $labels = [$tenant->getDefaultSnippetLocale() => $key, 'en-GB' => $key];
            $config = [
                'originalKey' => $key,
                'entityType' => $entityType,
                'sourceConnectionId' => (string) $connection->getId(),
                'readOnly' => $type === 'json',
            ];
            $field ??= new CustomField(
                $tenant,
                $set,
                $name,
                $type,
                $labels,
                $config,
                count($values),
            );
            $field->update($name, $type, $labels, $config, $field->getPosition());
            $manager->persist($field);
            $existing[$name] = $value;
        }

        return $existing;
    }
}
