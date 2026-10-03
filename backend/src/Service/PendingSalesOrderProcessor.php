<?php

namespace App\Service;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationSalesSyncCursor;
use App\Entity\SalesOrder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/** Shared, bounded round-robin retry after new warehouse stock becomes available. */
final class PendingSalesOrderProcessor
{
    public function __construct(private readonly SalesOrderIngestionService $ingestion)
    {
    }

    public function process(
        IntegrationConnection $connection,
        IntegrationSalesSyncCursor $cursor,
        EntityManagerInterface $manager,
        ?callable $refreshOrder = null,
    ): void
    {
        $afterId = $cursor->getLastPendingOrderId();
        $sql = <<<'SQL'
        SELECT id
        FROM sales_orders
        WHERE tenant_id = CAST(:tenantId AS uuid)
          AND connection_id = CAST(:connectionId AS uuid)
          AND status IN ('new', 'reserved', 'partially_fulfilled')
          AND EXISTS (
              SELECT 1 FROM sales_order_items item
              WHERE item.sales_order_id = sales_orders.id
                AND item.line_type = 'product'
                AND item.quantity > item.reserved_quantity + item.fulfilled_quantity
          )
        SQL;
        $parameters = [
            'tenantId' => (string) $connection->getTenant()->getId(),
            'connectionId' => (string) $connection->getId(),
        ];
        if ($afterId !== null) {
            $sql .= ' AND id > CAST(:afterId AS uuid)';
            $parameters['afterId'] = $afterId;
        }
        $sql .= ' ORDER BY id LIMIT 100';
        $ids = $manager->getConnection()->executeQuery($sql, $parameters)->fetchFirstColumn();
        foreach ($ids as $id) {
            $order = $manager->find(SalesOrder::class, Uuid::fromString($id));
            if ($order instanceof SalesOrder && $refreshOrder !== null) {
                $refreshOrder($order);
            }
            if ($order instanceof SalesOrder && $this->ingestion->retryAllocation($order, $manager)) {
                $this->ingestion->reconcileDeliveryFulfillment($order, $manager);
            }
        }
        $cursor->markPendingOrderId($ids === [] ? null : (string) end($ids));
        $manager->flush();
    }
}
