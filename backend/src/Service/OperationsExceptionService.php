<?php

namespace App\Service;

use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;

final class OperationsExceptionService
{
    public const TYPES = [
        'unmatched_products',
        'unallocated_orders',
        'shipment_reconciliation',
        'stock_sync_failed',
        'sales_sync_failed',
        'import_failed',
        'stock_discrepancy',
        'quarantined_returns',
    ];

    /** @return array{issues: list<array<string, mixed>>, pagination: array<string, int|bool>} */
    public function list(
        Tenant $tenant,
        string $type,
        int $page,
        int $limit,
        EntityManagerInterface $entityManager,
    ): array {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Unknown exception type.');
        }
        $page = max(1, $page);
        $limit = min(max(1, $limit), 100);
        $sql = $this->query($type);
        $database = $entityManager->getConnection();
        $parameters = ['tenantId' => $tenant->getId()->toRfc4122()];
        $total = (int) $database->fetchOne(
            'SELECT COUNT(*) FROM ('.$sql.') issues',
            $parameters,
        );
        $rows = $database->fetchAllAssociative(
            $sql.' ORDER BY occurred_at DESC, id DESC LIMIT :limit OFFSET :offset',
            [
                ...$parameters,
                'limit' => $limit,
                'offset' => ($page - 1) * $limit,
            ],
            [
                'limit' => \Doctrine\DBAL\ParameterType::INTEGER,
                'offset' => \Doctrine\DBAL\ParameterType::INTEGER,
            ],
        );

        return [
            'issues' => array_map(static fn (array $row): array => [
                'id' => $row['id'],
                'reference' => $row['reference'],
                'detail' => $row['detail'],
                'occurredAt' => $row['occurred_at'],
                'targetId' => $row['target_id'],
            ], $rows),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'hasMore' => $page * $limit < $total,
            ],
        ];
    }

    private function query(string $type): string
    {
        return match ($type) {
            'unmatched_products' => <<<'SQL'
SELECT orders.id::text AS id, orders.external_number AS reference,
       COUNT(items.id)::text AS detail, orders.updated_at AS occurred_at,
       orders.id::text AS target_id
FROM sales_orders orders
JOIN sales_order_items items ON items.sales_order_id = orders.id
WHERE orders.tenant_id = :tenantId
  AND orders.status NOT IN ('historical', 'cancelled', 'fulfilled')
  AND items.line_type = 'product' AND items.product_id IS NULL
GROUP BY orders.id
SQL,
            'unallocated_orders' => <<<'SQL'
SELECT orders.id::text AS id, orders.external_number AS reference,
       COALESCE(SUM(items.quantity - items.reserved_quantity - items.fulfilled_quantity), 0)::text AS detail,
       orders.updated_at AS occurred_at, orders.id::text AS target_id
FROM sales_orders orders
JOIN sales_order_items items ON items.sales_order_id = orders.id
WHERE orders.tenant_id = :tenantId
  AND orders.status IN ('new', 'partially_fulfilled')
  AND items.line_type = 'product' AND items.product_id IS NOT NULL
  AND items.quantity > items.reserved_quantity + items.fulfilled_quantity
  AND NOT EXISTS (
      SELECT 1 FROM sales_order_items missing
      WHERE missing.sales_order_id = orders.id
        AND missing.line_type = 'product' AND missing.product_id IS NULL
  )
GROUP BY orders.id
SQL,
            'shipment_reconciliation' => <<<'SQL'
SELECT orders.id::text AS id, orders.external_number AS reference,
       'shipment' AS detail, orders.updated_at AS occurred_at,
       orders.id::text AS target_id
FROM sales_orders orders
WHERE orders.tenant_id = :tenantId
  AND orders.status NOT IN ('historical', 'cancelled')
  AND (
      (
          orders.source_payload::jsonb->>'shipmentQuantitiesUnavailable' = 'true'
          AND orders.source_payload::jsonb->>'state' = 'completed'
          AND orders.status <> 'fulfilled'
      )
      OR
      EXISTS (
          SELECT 1 FROM sales_order_deliveries delivery
          WHERE delivery.sales_order_id = orders.id AND delivery.state = 'shipped_partially'
      )
      OR (
          EXISTS (
              SELECT 1 FROM sales_order_deliveries delivery
              WHERE delivery.sales_order_id = orders.id AND delivery.state = 'shipped'
                AND json_array_length(delivery.positions_snapshot) = 0
          )
          AND (
              EXISTS (
                  SELECT 1 FROM sales_order_deliveries delivery
                  WHERE delivery.sales_order_id = orders.id
                    AND delivery.state IS DISTINCT FROM 'shipped'
              )
              OR EXISTS (
                  SELECT 1 FROM sales_order_deliveries delivery
                  WHERE delivery.sales_order_id = orders.id AND delivery.state = 'shipped'
                    AND json_array_length(delivery.positions_snapshot) > 0
              )
          )
      )
  )
SQL,
            'stock_sync_failed' => <<<'SQL'
SELECT event.id::text AS id, COALESCE(product.sku, product.id::text) AS reference,
       COALESCE(event.last_error, 'Stock publication failed; inspect the worker log.') AS detail,
       event.updated_at AS occurred_at, product.id::text AS target_id
FROM inventory_sync_outbox event
JOIN products product ON product.id = event.product_id
WHERE event.tenant_id = :tenantId AND event.status = 'failed'
SQL,
            'sales_sync_failed' => <<<'SQL'
SELECT cursor.id::text AS id, connection.name AS reference,
       cursor.last_error AS detail, cursor.last_error_at AS occurred_at,
       connection.id::text AS target_id
FROM integration_sales_sync_cursors cursor
JOIN integration_connections connection ON connection.id = cursor.connection_id
WHERE connection.tenant_id = :tenantId AND cursor.last_error IS NOT NULL
SQL,
            'import_failed' => <<<'SQL'
SELECT run.id::text AS id,
       connection.name || ' · ' || run.type AS reference,
       COALESCE(run.failure_reason, run.failed_items::text || ' failed item(s)') AS detail,
       run.updated_at AS occurred_at, connection.id::text AS target_id
FROM integration_import_runs run
JOIN integration_connections connection ON connection.id = run.connection_id
WHERE run.tenant_id = :tenantId
  AND (run.status = 'failed' OR run.failed_items > 0)
  AND NOT EXISTS (
      SELECT 1 FROM integration_import_runs newer
      WHERE newer.connection_id = run.connection_id
        AND newer.type = run.type
        AND (
            newer.created_at > run.created_at
            OR (newer.created_at = run.created_at AND newer.id > run.id)
        )
  )
SQL,
            'stock_discrepancy' => <<<'SQL'
SELECT level.id::text AS id,
       COALESCE(product.sku, product.id::text) || ' · ' || warehouse.name AS reference,
       (level.quantity - level.reserved_quantity - level.unavailable_quantity)::text AS detail,
       level.updated_at AS occurred_at, warehouse.id::text AS target_id
FROM inventory_levels level
JOIN products product ON product.id = level.product_id
JOIN warehouses warehouse ON warehouse.id = level.warehouse_id
WHERE level.tenant_id = :tenantId
  AND level.quantity < level.reserved_quantity + level.unavailable_quantity
SQL,
            'quarantined_returns' => <<<'SQL'
SELECT return_record.id::text AS id, orders.external_number AS reference,
       item.name || ' · ' || return_record.quantity::text AS detail,
       return_record.created_at AS occurred_at, orders.id::text AS target_id
FROM sales_returns return_record
JOIN sales_orders orders ON orders.id = return_record.sales_order_id
JOIN sales_order_items item ON item.id = return_record.sales_order_item_id
WHERE return_record.tenant_id = :tenantId
  AND return_record.disposition = 'quarantine'
SQL,
        };
    }
}
