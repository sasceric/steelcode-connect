<?php

namespace App\Service;

use App\Entity\SalesOrder;
use App\Entity\SalesOrderAllocation;

final class SalesPickListService
{
    /** @return list<array<string, string>> */
    public function lines(SalesOrder $order): array
    {
        if (!in_array($order->getStatus(), ['reserved', 'partially_fulfilled'], true)) {
            return [];
        }

        $lines = [];
        foreach ($order->getItems() as $item) {
            foreach ($item->getAllocations() as $allocation) {
                if (!$allocation instanceof SalesOrderAllocation || $allocation->getStatus() !== 'reserved') {
                    continue;
                }

                $warehouse = $allocation->getWarehouse();
                $key = $warehouse->getId()->toRfc4122().':'.$item->getId()->toRfc4122();
                if (!isset($lines[$key])) {
                    $lines[$key] = [
                        'itemId' => $item->getId()->toRfc4122(),
                        'warehouseId' => $warehouse->getId()->toRfc4122(),
                        'warehouse' => $warehouse->getName(),
                        'warehouseCode' => $warehouse->getCode(),
                        'sku' => $item->getSku() ?? '',
                        'name' => $item->getName(),
                        'quantity' => '0.0000',
                    ];
                }

                $lines[$key]['quantity'] = number_format(
                    (float) $lines[$key]['quantity'] + (float) $allocation->getQuantity(),
                    4,
                    '.',
                    '',
                );
            }
        }

        $result = array_values($lines);
        usort($result, static fn (array $left, array $right): int => [
            $left['warehouse'],
            $left['sku'],
            $left['name'],
        ] <=> [
            $right['warehouse'],
            $right['sku'],
            $right['name'],
        ]);

        return $result;
    }
}
