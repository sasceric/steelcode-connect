<?php

namespace App\Integration;

use App\Entity\IntegrationConnection;
use Doctrine\DBAL\Connection;

final class CatalogueExportSelection
{
    /** Materialize a deduplicated selection in the database, not in PHP/browser memory. */
    public function selectedIds(
        Connection $database,
        IntegrationConnection $connection,
        array $settings,
    ): \Traversable
    {
        [$sql, $parameters] = $this->query($connection, $settings);

        yield from $database->executeQuery($sql.' ORDER BY p.parent_id NULLS FIRST, p.id', $parameters)->iterateColumn();
    }

    public function count(
        Connection $database,
        IntegrationConnection $connection,
        array $settings,
    ): int
    {
        [$sql, $parameters] = $this->query($connection, $settings);

        return (int) $database->fetchOne('SELECT COUNT(*) FROM ('.$sql.') selected_products', $parameters);
    }

    /** Keyset pagination keeps reconciliation bounded even for very large catalogues. */
    public function page(
        Connection $database,
        IntegrationConnection $connection,
        array $settings,
        ?string $after,
        int $limit = 100,
    ): array
    {
        [$sql, $parameters] = $this->query($connection, $settings);
        $parameters['limit'] = min(100, max(1, $limit));
        $where = '';
        if ($after !== null) {
            $parameters['after'] = $after;
            $where = ' WHERE id > :after';
        }

        return $database->fetchFirstColumn(
            'SELECT id FROM ('.$sql.') selected_products'.$where.' ORDER BY id LIMIT :limit',
            $parameters,
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER],
        );
    }

    public function matchingIds(
        Connection $database,
        IntegrationConnection $connection,
        array $settings,
        array $ids,
    ): array
    {
        if ($ids === []) {
            return [];
        }
        [$sql, $parameters] = $this->query($connection, $settings);
        $names = [];
        foreach ($ids as $index => $id) {
            $parameters['candidate'.$index] = $id;
            $names[] = ':candidate'.$index;
        }

        return $database->fetchFirstColumn('SELECT id FROM ('.$sql.') selected_products WHERE id IN ('.implode(', ', $names).')', $parameters);
    }

    private function query(IntegrationConnection $connection, array $settings): array
    {
        $tenant = $connection->getTenant()->getId()->toRfc4122();
        $parameters = ['tenant' => $tenant];
        $filters = [];
        $list = static function (array $ids, string $prefix) use (&$parameters): string {
            $names = [];
            foreach ($ids as $index => $id) {
                $name = $prefix.$index;
                $parameters[$name] = $id;
                $names[] = ':'.$name;
            }

            return $names === [] ? 'NULL' : implode(', ', $names);
        };
        $categories = $list($settings['categoryIds'], 'category');
        $recursive = $settings['includeDescendants']
            ? 'UNION SELECT c.id FROM categories c JOIN category_tree t ON c.parent_id = t.id WHERE c.tenant_id = :tenant'
            : '';
        if ($settings['categoryIds'] !== []) {
            $filters[] = 'EXISTS (SELECT 1 FROM category_products cp WHERE cp.product_id IN (p.id, p.parent_id) AND cp.category_id IN (SELECT id FROM category_tree))';
        }
        if ($settings['brandIds'] !== []) {
            $brands = $list($settings['brandIds'], 'brand');
            $filters[] = "EXISTS (SELECT 1 FROM product_brands pb WHERE pb.product_id IN (p.id, p.parent_id) AND pb.tenant_id = :tenant AND pb.brand_id IN ($brands))";
        }
        if ($settings['manufacturerIds'] !== []) {
            $manufacturers = $list($settings['manufacturerIds'], 'manufacturer');
            $filters[] = "COALESCE(p.manufacturer_id, parent.manufacturer_id) IN ($manufacturers)";
        }
        $manual = $list($settings['productIds'], 'product');
        $excluded = $list($settings['excludeIds'], 'exclude');
        $filter = ($settings['scope'] ?? 'selected') === 'all'
            ? 'p.parent_id IS NULL'
            : ($filters === [] ? 'FALSE' : '(p.parent_id IS NULL AND '.implode(' AND ', $filters).')');
        $children = $settings['includeVariants']
            ? 'UNION SELECT child.id FROM products child JOIN selected s ON child.parent_id = s.id WHERE child.tenant_id = :tenant'
            : '';
        $sql = <<<SQL
WITH RECURSIVE category_tree AS (
    SELECT id FROM categories WHERE tenant_id = :tenant AND id IN ($categories)
    $recursive
), selected AS (
    SELECT p.id FROM products p LEFT JOIN products parent ON parent.id = p.parent_id AND parent.tenant_id = :tenant
    WHERE p.tenant_id = :tenant AND ($filter OR p.id IN ($manual))
), expanded AS (
    SELECT id FROM selected
    $children
), ancestors AS (
    SELECT id FROM expanded WHERE id NOT IN ($excluded)
    UNION
    SELECT p.parent_id FROM products p JOIN ancestors a ON a.id = p.id
    WHERE p.tenant_id = :tenant AND p.parent_id IS NOT NULL
)
SELECT p.id FROM products p JOIN ancestors a ON a.id = p.id
WHERE p.tenant_id = :tenant
SQL;
        // NOT IN (NULL) would exclude everything; empty exclusions mean no exclusion.
        $sql = str_replace('id NOT IN (NULL)', 'TRUE', $sql);

        return [$sql, $parameters];
    }
}
