<?php

namespace App\Service;

use App\Entity\IntegrationConnection;
use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Entity\Product;
use App\Entity\SalesOrder;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class SalesOrderIngestionService
{
    public function __construct(
        private readonly SalesOrderAllocationService $allocation,
    ) {
    }

    /**
     * Receives a connector-normalized order event. Connector adapters must map
     * provider-specific payloads into this contract before calling this method.
     *
     * @param array{
     *     type: 'placed'|'cancelled'|'shipped',
     *     externalId: string,
     *     externalNumber?: string,
     *     orderedAt?: string|null,
     *     lines?: list<array<string, mixed>>,
     *     customer?: array<string, mixed>,
     *     billingAddress?: array<string, mixed>,
     *     shippingAddress?: array<string, mixed>,
     *     commercial?: array<string, mixed>,
     *     sourcePayload?: array<string, mixed>
     * } $event
     */
    public function ingest(
        IntegrationConnection $connection,
        array $event,
        EntityManagerInterface $entityManager,
        bool $reserveStock = true,
    ): SalesOrder {
        $this->assertChannelConnection($connection);
        $type = $event['type'] ?? null;
        $externalId = trim((string) ($event['externalId'] ?? ''));
        if (!in_array($type, ['placed', 'cancelled', 'shipped'], true) || $externalId === '') {
            throw new \InvalidArgumentException('Invalid connector order event.');
        }

        $database = $entityManager->getConnection();
        $database->beginTransaction();
        try {
            $order = $entityManager->getRepository(SalesOrder::class)->findOneBy([
                'connection' => $connection,
                'externalId' => $externalId,
            ]);
            if ($order instanceof SalesOrder) {
                $this->lockOrder($order, $entityManager);
            }

            if ($type === 'placed') {
                if (!$order instanceof SalesOrder) {
                    $order = $this->createOrder($connection, $event, $entityManager, true);
                    $entityManager->persist($order);

                    if ($reserveStock) {
                        try {
                            $this->allocation->reserve($order, null, $entityManager);
                        } catch (\DomainException) {
                            // The accepted order remains unallocated until stock arrives.
                        }
                    } else {
                        $order->markHistorical();
                    }
                } elseif (!$reserveStock) {
                    if ($order->getStatus() === 'historical') {
                        $this->syncLines($order, $event['lines'] ?? [], $entityManager, true);
                        $this->applyCommercialSnapshot($order, $connection, $event, $entityManager);
                        $this->snapshotPayments($order, $event['payments'] ?? []);
                        $this->snapshotDeliveries($order, $event['deliveries'] ?? []);
                        $order->updateSourcePayload($this->snapshot($event['sourcePayload'] ?? []));
                    }
                } elseif ($order->getStatus() !== 'historical') {
                    $this->reconcileLiveLines($order, $event['lines'] ?? [], $entityManager);
                    $this->applyCommercialSnapshot($order, $connection, $event, $entityManager);
                    $this->snapshotPayments($order, $event['payments'] ?? []);
                    $this->snapshotDeliveries($order, $event['deliveries'] ?? []);
                    $order->updateSourcePayload($this->snapshot($event['sourcePayload'] ?? []));
                    if ($order->getStatus() !== 'cancelled') {
                        $this->allocation->reserveOpen($order, null, $entityManager);
                    }
                }
            } elseif (!$order instanceof SalesOrder) {
                throw new \DomainException('The channel order does not exist.');
            } elseif ($order->getStatus() === 'historical') {
                $order->updateHistoricalState($type === 'cancelled' ? 'cancelled' : 'completed');
            } elseif ($type === 'cancelled' && $order->getStatus() === 'fulfilled') {
                $order->updateSourcePayload(array_merge($order->getSourcePayload(), ['state' => 'cancelled']));
            } elseif ($type === 'cancelled' && $order->getStatus() !== 'cancelled') {
                $this->allocation->cancel($order, null, $entityManager);
            } elseif ($type === 'shipped' && !in_array($order->getStatus(), ['cancelled', 'fulfilled'], true)) {
                $targets = [];
                $shipmentLines = $event['lines'] ?? null;
                if ($shipmentLines !== null && !is_array($shipmentLines)) {
                    throw new \InvalidArgumentException('Invalid shipment lines.');
                }
                if (is_array($shipmentLines)) {
                    foreach ($shipmentLines as $line) {
                        if (!is_array($line)) {
                            throw new \InvalidArgumentException('Invalid shipment line.');
                        }
                        $lineId = trim((string) ($line['externalLineId'] ?? ''));
                        if ($lineId === '' || isset($targets[$lineId])) {
                            throw new \InvalidArgumentException('Invalid shipment line ID.');
                        }
                        $targets[$lineId] = $this->quantity($line['quantity'] ?? null);
                    }
                } else {
                    foreach ($order->getItems() as $item) {
                        if ($item->isInventoryLine()) {
                            $targets[$item->getExternalLineId()] = $item->getQuantity();
                        }
                    }
                }
                $this->shipToTargets($order, $targets, $entityManager);
            }

            $entityManager->flush();
            $database->commit();

            return $order;
        } catch (\Throwable $exception) {
            $database->rollBack();

            throw $exception;
        }
    }

    /** @param list<array<string, mixed>> $lines */
    private function reconcileLiveLines(
        SalesOrder $order,
        array $lines,
        EntityManagerInterface $entityManager,
    ): void
    {
        if ($lines === []) {
            throw new \InvalidArgumentException('A placed order requires at least one line.');
        }

        $existing = [];
        foreach ($order->getItems() as $item) {
            $existing[$item->getExternalLineId()] = $item;
        }

        $incoming = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                throw new \InvalidArgumentException('Invalid channel order line.');
            }
            $externalLineId = trim((string) ($line['externalLineId'] ?? ''));
            $lineType = $this->nullableString($line['type'] ?? 'product', 64) ?? 'product';
            $name = trim((string) ($line['name'] ?? ''));
            $quantity = $this->quantity($line['quantity'] ?? null);
            if ($externalLineId === '' || $name === '' || isset($incoming[$externalLineId])) {
                throw new \InvalidArgumentException('Invalid channel order line.');
            }
            $sku = $this->nullableString($line['sku'] ?? null, 255);
            $incoming[$externalLineId] = [
                'line' => $line,
                'type' => $lineType,
                'name' => $name,
                'sku' => $sku,
                'quantity' => $quantity,
            ];
        }

        $structureChanged = count($existing) !== count($incoming);
        foreach ($existing as $externalLineId => $item) {
            $replacement = $incoming[$externalLineId] ?? null;
            if ($replacement === null) {
                if ((float) $item->getFulfilledQuantity() > 0.00001) {
                    throw new \DomainException('A shipped order line cannot be removed automatically.');
                }
                $structureChanged = true;
                continue;
            }
            if (
                $item->getSku() !== $replacement['sku']
                || $item->getLineType() !== $replacement['type']
                || $item->getQuantity() !== $replacement['quantity']
            ) {
                $structureChanged = true;
                if (
                    (float) $item->getFulfilledQuantity() > 0.00001
                    && (
                        $item->getSku() !== $replacement['sku']
                        || $item->getLineType() !== $replacement['type']
                        || (float) $replacement['quantity'] + 0.00001 < (float) $item->getFulfilledQuantity()
                    )
                ) {
                    throw new \DomainException('A shipped order line cannot change product or fall below its shipped quantity.');
                }
            }
        }
        if ($order->getStatus() === 'cancelled' && $structureChanged) {
            throw new \DomainException('A cancelled order cannot change its product lines automatically.');
        }
        if ($structureChanged) {
            $this->allocation->releaseOpenReservations($order, null, $entityManager);
        }

        foreach ($existing as $externalLineId => $item) {
            if (!isset($incoming[$externalLineId])) {
                $order->removeUnfulfilledItem($item);
            }
        }
        foreach ($incoming as $externalLineId => $entry) {
            $item = $existing[$externalLineId] ?? null;
            $line = $entry['line'];
            $product = null;
            if ($entry['type'] === 'product' && $entry['sku'] !== null) {
                $product = $entityManager->getRepository(Product::class)->findOneBy([
                    'tenant' => $order->getTenant(),
                    'sku' => $entry['sku'],
                ]);
                if (!$product instanceof Product && $item instanceof \App\Entity\SalesOrderItem && $item->getSku() === $entry['sku']) {
                    $product = $item->getProduct();
                }
            }
            $sourcePayload = $this->snapshot($line['sourcePayload'] ?? []);
            if (!$item instanceof \App\Entity\SalesOrderItem) {
                $item = $order->addItem(
                    $product,
                    $externalLineId,
                    $entry['sku'],
                    $entry['name'],
                    $entry['quantity'],
                    $entry['type'],
                    $sourcePayload,
                );
            } elseif ($structureChanged) {
                $item->refreshLiveSnapshot(
                    $product,
                    $entry['sku'],
                    $entry['name'],
                    $entry['quantity'],
                    $entry['type'],
                    $sourcePayload,
                );
            } else {
                $item->updateDisplaySnapshot($entry['name'], $sourcePayload);
            }

            $commercial = $this->snapshot($line['commercial'] ?? []);
            $item->setCommercialSnapshot(
                $this->nullableString($commercial['currencyCode'] ?? null, 3),
                $this->decimal($commercial['unitNet'] ?? null),
                $this->decimal($commercial['unitGross'] ?? null),
                $this->decimal($commercial['totalNet'] ?? null),
                $this->decimal($commercial['totalTax'] ?? null),
                $this->decimal($commercial['totalGross'] ?? null),
                $this->decimal($commercial['discountGross'] ?? null),
                $this->snapshot($commercial['taxes'] ?? []),
            );
        }
        $manualTargets = $order->getManualShipmentTargets();
        if ($manualTargets !== []) {
            $updatedTargets = [];
            foreach ($incoming as $externalLineId => $entry) {
                if ($entry['type'] === 'product') {
                    $updatedTargets[$externalLineId] = $manualTargets[$externalLineId] ?? '0.0000';
                }
            }
            $order->setManualShipmentTargets($updatedTargets);
        }
        $order->refreshInventoryStatus();
    }

    public function reconcileDeliveryFulfillment(
        SalesOrder $order,
        EntityManagerInterface $entityManager,
        ?User $user = null,
    ): void {
        $database = $entityManager->getConnection();
        $database->beginTransaction();
        try {
            $this->lockOrder($order, $entityManager);
            if (in_array($order->getStatus(), ['historical', 'cancelled'], true)) {
                $database->commit();

                return;
            }
            $deliveries = $order->getDeliveries();
            $allShipped = !$deliveries->isEmpty();
            $hasShipped = false;
            $targets = [];
            foreach ($deliveries as $delivery) {
                if ($delivery->getState() === 'shipped_partially') {
                    $allShipped = false;
                    continue;
                }
                if ($delivery->getState() !== 'shipped') {
                    $allShipped = false;
                    continue;
                }
                $hasShipped = true;
                $positions = $delivery->getPositionsSnapshot();
                if ($positions === []) {
                    continue;
                }
                foreach ($positions as $position) {
                    if (!is_array($position)) {
                        throw new \InvalidArgumentException('Invalid shipped delivery position.');
                    }
                    $lineId = trim((string) ($position['externalLineId'] ?? ''));
                    $quantity = $this->quantity($position['quantity'] ?? null);
                    if ($lineId === '') {
                        throw new \InvalidArgumentException('A shipped delivery position has no order line ID.');
                    }
                    $targets[$lineId] = number_format(
                        (float) ($targets[$lineId] ?? '0.0000') + (float) $quantity,
                        4,
                        '.',
                        '',
                    );
                }
            }
            $manualTargets = $order->needsShipmentReconciliation()
                ? $order->getManualShipmentTargets()
                : [];
            if (!$hasShipped && $manualTargets === []) {
                $database->commit();

                return;
            }
            if ($allShipped && $targets === []) {
                foreach ($order->getItems() as $item) {
                    if ($item->isInventoryLine()) {
                        $targets[$item->getExternalLineId()] = $item->getQuantity();
                    }
                }
            }
            foreach ($manualTargets as $lineId => $quantity) {
                $targets[$lineId] = number_format(
                    max((float) ($targets[$lineId] ?? '0.0000'), (float) $quantity),
                    4,
                    '.',
                    '',
                );
            }

            $this->shipToTargets(
                $order,
                $targets,
                $entityManager,
                $user,
                $user instanceof User ? 'Manual partial shipment reconciliation' : 'Channel order shipment',
            );
            $entityManager->flush();
            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();

            throw $exception;
        }
    }

    /** @param array<string, mixed> $targets */
    public function reconcileManualShipment(
        SalesOrder $order,
        array $targets,
        EntityManagerInterface $entityManager,
        ?User $user = null,
    ): void {
        $database = $entityManager->getConnection();
        $database->beginTransaction();
        try {
            $this->lockOrder($order, $entityManager);
            if (
                !$order->needsShipmentReconciliation()
                || in_array($order->getStatus(), ['historical', 'cancelled'], true)
            ) {
                throw new \DomainException('This order has no active partial shipment to reconcile.');
            }

            $items = [];
            foreach ($order->getItems() as $item) {
                if ($item->isInventoryLine()) {
                    $items[$item->getExternalLineId()] = $item;
                }
            }
            if (count($targets) !== count($items)) {
                throw new \InvalidArgumentException('Provide cumulative shipped quantities for every product line.');
            }

            $normalized = [];
            foreach ($targets as $lineId => $quantity) {
                if (!is_string($lineId) || !isset($items[$lineId]) || !is_numeric($quantity)) {
                    throw new \InvalidArgumentException('Invalid partial shipment line or quantity.');
                }
                $number = (float) $quantity;
                if (
                    !is_finite($number)
                    || $number < 0
                    || $number > (float) $items[$lineId]->getQuantity() + 0.00001
                    || $number + 0.00001 < (float) $items[$lineId]->getFulfilledQuantity()
                ) {
                    throw new \DomainException('The cumulative shipped quantity is outside the order line range.');
                }
                $normalized[$lineId] = number_format($number, 4, '.', '');
            }

            $order->setManualShipmentTargets($normalized);
            $entityManager->flush();
            $this->reconcileDeliveryFulfillment($order, $entityManager, $user);
            $entityManager->flush();
            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();

            throw $exception;
        }
    }

    private function lockOrder(SalesOrder $order, EntityManagerInterface $entityManager): void
    {
        $entityManager->lock($order, LockMode::PESSIMISTIC_WRITE);
        $entityManager->refresh($order);
        foreach ($order->getItems() as $item) {
            $entityManager->refresh($item);
            foreach ($item->getAllocations() as $allocation) {
                $entityManager->refresh($allocation);
            }
        }
        foreach ($order->getDeliveries() as $delivery) {
            $entityManager->refresh($delivery);
        }
    }

    /** @param array<string, string> $targets */
    private function shipToTargets(
        SalesOrder $order,
        array $targets,
        EntityManagerInterface $entityManager,
        ?User $user = null,
        string $note = 'Channel order shipment',
    ): void {
        $items = [];
        foreach ($order->getItems() as $item) {
            $items[$item->getExternalLineId()] = $item;
        }
        foreach ($targets as $lineId => $quantity) {
            if (!isset($items[$lineId])) {
                throw new \DomainException('A shipped delivery references an unknown order line.');
            }
            $item = $items[$lineId];
            if (!$item->isInventoryLine()) {
                continue;
            }
            if ((float) $quantity > (float) $item->getQuantity() + 0.00001) {
                throw new \DomainException('A shipped quantity exceeds the order line quantity.');
            }
            if ((float) $quantity + 0.00001 < (float) $item->getFulfilledQuantity()) {
                throw new \DomainException('The source reduced an already shipped quantity; manual return reconciliation is required.');
            }
        }

        foreach ($targets as $lineId => $quantity) {
            $item = $items[$lineId];
            if (!$item->isInventoryLine()) {
                continue;
            }
            $delta = (float) $quantity - (float) $item->getFulfilledQuantity();
            if ($delta > (float) $item->getReservedQuantity() + 0.00001) {
                if ($this->allocation->reserveOpen($order, null, $entityManager)) {
                    $entityManager->flush();
                }
                break;
            }
        }

        foreach ($targets as $lineId => $quantity) {
            $item = $items[$lineId];
            if (!$item->isInventoryLine()) {
                continue;
            }
            $delta = (float) $quantity - (float) $item->getFulfilledQuantity();
            if ($delta <= 0.00001) {
                continue;
            }
            if ($delta > (float) $item->getReservedQuantity() + 0.00001) {
                throw new \DomainException('The shipped order line has no matching warehouse reservation.');
            }
            $this->allocation->shipLineQuantity(
                $item,
                number_format($delta, 4, '.', ''),
                $user,
                $entityManager,
                $note,
            );
        }
        $order->refreshInventoryStatus();
    }

    public function retryAllocation(SalesOrder $order, EntityManagerInterface $entityManager): bool
    {
        if (!in_array($order->getStatus(), ['new', 'reserved', 'partially_fulfilled'], true)) {
            return false;
        }

        $database = $entityManager->getConnection();
        $database->beginTransaction();
        try {
            $this->lockOrder($order, $entityManager);
            if (!in_array($order->getStatus(), ['new', 'reserved', 'partially_fulfilled'], true)) {
                $database->commit();

                return false;
            }
            foreach ($order->getItems() as $item) {
                if (!$item->isInventoryLine() || $item->isProductResolved()) {
                    continue;
                }

                $sku = $item->getSku();
                if ($sku === null) {
                    continue;
                }
                $product = $entityManager->getRepository(Product::class)->findOneBy([
                    'tenant' => $order->getTenant(),
                    'sku' => $sku,
                ]);
                if ($product instanceof Product) {
                    $item->refreshSnapshot(
                        $product,
                        $sku,
                        $item->getName(),
                        $item->getQuantity(),
                        $item->getLineType(),
                        $item->getSourcePayload(),
                    );
                }
            }

            $this->allocation->reserveOpen($order, null, $entityManager);

            $entityManager->flush();
            $database->commit();

            return (float) $order->getOutstandingQuantity() <= 0.00001;
        } catch (\Throwable $exception) {
            $database->rollBack();

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $customerPayload
     * @param array<string, mixed> $billingAddress
     * @param array<string, mixed> $shippingAddress
     * @param list<array<string, mixed>> $otherAddresses
     */
    public function ingestCustomer(
        IntegrationConnection $connection,
        array $customerPayload,
        array $billingAddress,
        array $shippingAddress,
        EntityManagerInterface $entityManager,
        array $otherAddresses = [],
        bool $addressBookComplete = false,
    ): Customer {
        $this->assertChannelConnection($connection);
        if ($this->nullableString($customerPayload['externalId'] ?? null, 128) === null) {
            throw new \InvalidArgumentException('An imported customer requires an external ID.');
        }

        $database = $entityManager->getConnection();
        $database->beginTransaction();
        try {
            $customerPayload['defaultBillingAddressId'] ??= $billingAddress['externalId'] ?? null;
            $customerPayload['defaultShippingAddressId'] ??= $shippingAddress['externalId'] ?? null;
            $customer = $this->upsertCustomerProfile($connection, $customerPayload, $entityManager);
            $billingId = $this->nullableString(
                $customerPayload['defaultBillingAddressId'] ?? $billingAddress['externalId'] ?? null,
                128,
            );
            $shippingId = $this->nullableString(
                $customerPayload['defaultShippingAddressId'] ?? $shippingAddress['externalId'] ?? null,
                128,
            );
            $addresses = [];
            foreach ([$billingAddress, $shippingAddress, ...$otherAddresses] as $address) {
                if (!is_array($address) || $address === []) {
                    continue;
                }
                $addressId = $this->nullableString($address['externalId'] ?? null, 128);
                if ($addressId !== null) {
                    $addresses[$addressId] = $address;
                }
            }
            foreach ($customer->getAddresses() as $existingAddress) {
                $addressId = $existingAddress->getExternalId();
                if ($addressBookComplete && !isset($addresses[$addressId ?? ''])) {
                    $customer->removeAddress($existingAddress);
                    $entityManager->remove($existingAddress);
                    continue;
                }
                $existingAddress->setDefaultFlags($addressId === $billingId, $addressId === $shippingId);
            }
            foreach ($addresses as $addressId => $address) {
                $this->upsertAddress(
                    $customer,
                    $address,
                    $addressId === $billingId,
                    $addressId === $shippingId,
                    $entityManager,
                );
            }

            $entityManager->flush();
            $database->commit();

            return $customer;
        } catch (\Throwable $exception) {
            $database->rollBack();

            throw $exception;
        }
    }

    /**
     * @param array{
     *     externalId: string,
     *     externalNumber?: string,
     *     orderedAt?: string|null,
     *     lines?: list<array<string, mixed>>,
     *     sourcePayload?: array<string, mixed>
     * } $event
     */
    private function createOrder(
        IntegrationConnection $connection,
        array $event,
        EntityManagerInterface $entityManager,
        bool $allowUnmatchedProducts = false,
    ): SalesOrder {
        $lines = $event['lines'] ?? [];
        if (!is_array($lines) || $lines === []) {
            throw new \InvalidArgumentException('A placed order requires at least one line.');
        }

        $orderedAt = $this->orderedAt($event['orderedAt'] ?? null);
        $externalId = trim((string) ($event['externalId'] ?? ''));
        $order = new SalesOrder(
            $connection->getTenant(),
            $connection,
            $externalId,
            trim((string) ($event['externalNumber'] ?? $externalId)) ?: $externalId,
            is_array($event['sourcePayload'] ?? null) ? $event['sourcePayload'] : [],
            $orderedAt,
        );
        $this->applyCommercialSnapshot($order, $connection, $event, $entityManager);
        $this->snapshotPayments($order, $event['payments'] ?? []);
        $this->snapshotDeliveries($order, $event['deliveries'] ?? []);
        $this->syncLines($order, $lines, $entityManager, $allowUnmatchedProducts);

        return $order;
    }

    /** @param list<array<string, mixed>> $lines */
    private function syncLines(
        SalesOrder $order,
        array $lines,
        EntityManagerInterface $entityManager,
        bool $allowUnmatchedProducts,
    ): void {
        if ($lines === []) {
            throw new \InvalidArgumentException('A placed order requires at least one line.');
        }

        $existingByExternalId = [];
        foreach ($order->getItems() as $item) {
            $existingByExternalId[$item->getExternalLineId()] = $item;
        }
        $externalLineIds = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                throw new \InvalidArgumentException('Invalid channel order line.');
            }

            $externalLineId = trim((string) ($line['externalLineId'] ?? ''));
            $sku = $this->nullableString($line['sku'] ?? null, 255);
            $lineType = $this->nullableString($line['type'] ?? 'product', 64) ?? 'product';
            $name = trim((string) ($line['name'] ?? ''));
            $quantity = $this->quantity($line['quantity'] ?? null);
            if ($externalLineId === '' || $name === '' || isset($externalLineIds[$externalLineId])) {
                throw new \InvalidArgumentException('Invalid channel order line.');
            }

            $product = null;
            if ($lineType === 'product' && $sku !== null) {
                $product = $entityManager->getRepository(Product::class)->findOneBy([
                    'tenant' => $order->getTenant(),
                    'sku' => $sku,
                ]);
            }
            if ($lineType === 'product' && !$product instanceof Product && !$allowUnmatchedProducts) {
                throw new \DomainException(sprintf('No product matches channel SKU "%s".', $sku ?? 'missing'));
            }

            $externalLineIds[$externalLineId] = true;
            $sourcePayload = $this->snapshot($line['sourcePayload'] ?? []);
            $item = $existingByExternalId[$externalLineId] ?? null;
            if ($item === null) {
                $item = $order->addItem(
                    $product,
                    $externalLineId,
                    $sku,
                    $name,
                    $quantity,
                    $lineType,
                    $sourcePayload,
                );
            } else {
                $item->refreshSnapshot($product, $sku, $name, $quantity, $lineType, $sourcePayload);
            }
            $commercial = $this->snapshot($line['commercial'] ?? []);
            $item->setCommercialSnapshot(
                $this->nullableString($commercial['currencyCode'] ?? null, 3),
                $this->decimal($commercial['unitNet'] ?? null),
                $this->decimal($commercial['unitGross'] ?? null),
                $this->decimal($commercial['totalNet'] ?? null),
                $this->decimal($commercial['totalTax'] ?? null),
                $this->decimal($commercial['totalGross'] ?? null),
                $this->decimal($commercial['discountGross'] ?? null),
                $this->snapshot($commercial['taxes'] ?? []),
            );
        }
        if ($order->getStatus() === 'historical') {
            foreach ($existingByExternalId as $externalLineId => $item) {
                if (!isset($externalLineIds[$externalLineId])) {
                    $order->removeHistoricalItem($item);
                }
            }
        }
    }

    /**
     * Checkout snapshots belong to the order. The related Customer is a useful
     * current record for sales operations, but may change after checkout.
     *
     * @param array<string, mixed> $event
     */
    private function applyCommercialSnapshot(
        SalesOrder $order,
        IntegrationConnection $connection,
        array $event,
        EntityManagerInterface $entityManager,
    ): void {
        $customerPayload = $this->snapshot($event['customer'] ?? []);
        $billingAddress = $this->snapshot($event['billingAddress'] ?? []);
        $shippingAddress = $this->snapshot($event['shippingAddress'] ?? []);
        $commercial = $this->snapshot($event['commercial'] ?? []);
        $customer = $this->resolveOrderCustomer($connection, $customerPayload, $entityManager);

        $order->setCommercialSnapshot(
            $customer,
            $customerPayload,
            $billingAddress,
            $shippingAddress,
            $this->nullableString($commercial['currencyCode'] ?? null, 3),
            $this->nullableString($commercial['taxStatus'] ?? null, 16),
            $this->decimal($commercial['amountNet'] ?? null),
            $this->decimal($commercial['amountTax'] ?? null),
            $this->decimal($commercial['amountGross'] ?? null),
            $this->decimal($commercial['shippingNet'] ?? null),
            $this->decimal($commercial['shippingTax'] ?? null),
            $this->decimal($commercial['shippingGross'] ?? null),
        );
    }

    private function snapshotPayments(SalesOrder $order, mixed $payload): void
    {
        if ($payload === null) {
            return;
        }
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Invalid channel order payments.');
        }
        foreach ($payload as $entry) {
            if (!is_array($entry)) {
                throw new \InvalidArgumentException('Invalid channel order payment.');
            }
            $externalId = $this->nullableString($entry['externalId'] ?? null, 128);
            if ($externalId === null) {
                throw new \InvalidArgumentException('A channel order payment requires an external ID.');
            }
            $payment = null;
            foreach ($order->getPayments() as $existingPayment) {
                if ($existingPayment->getExternalId() === $externalId) {
                    $payment = $existingPayment;
                    break;
                }
            }
            $payment ??= $order->addPayment($externalId);
            $payment->snapshot(
                $this->nullableString($entry['methodExternalId'] ?? null, 128),
                $this->nullableString($entry['method'] ?? null, 255),
                $this->nullableString($entry['state'] ?? null, 64),
                $this->nullableString($entry['reference'] ?? null, 128),
                $this->nullableString($entry['currencyCode'] ?? null, 3),
                $this->decimal($entry['amount'] ?? null),
                $entry,
            );
        }
    }

    private function snapshotDeliveries(SalesOrder $order, mixed $payload): void
    {
        if ($payload === null) {
            return;
        }
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Invalid channel order deliveries.');
        }
        foreach ($payload as $entry) {
            if (!is_array($entry)) {
                throw new \InvalidArgumentException('Invalid channel order delivery.');
            }
            $externalId = $this->nullableString($entry['externalId'] ?? null, 128);
            if ($externalId === null) {
                throw new \InvalidArgumentException('A channel order delivery requires an external ID.');
            }
            $delivery = null;
            foreach ($order->getDeliveries() as $existingDelivery) {
                if ($existingDelivery->getExternalId() === $externalId) {
                    $delivery = $existingDelivery;
                    break;
                }
            }
            $delivery ??= $order->addDelivery($externalId);
            $trackingCodes = $entry['trackingCodes'] ?? [];
            if (!is_array($trackingCodes) || !array_is_list($trackingCodes)) {
                throw new \InvalidArgumentException('Invalid channel order tracking codes.');
            }
            if ($trackingCodes === [] && isset($entry['trackingNumber'])) {
                $trackingCodes = [$entry['trackingNumber']];
            }
            $positions = $entry['positions'] ?? [];
            if (!is_array($positions) || !array_is_list($positions)) {
                throw new \InvalidArgumentException('Invalid channel order delivery positions.');
            }
            $delivery->snapshot(
                $this->nullableString($entry['methodExternalId'] ?? null, 128),
                $this->nullableString($entry['method'] ?? null, 255),
                $this->nullableString($entry['state'] ?? null, 64),
                array_values(array_filter(array_map(
                    fn (mixed $code): ?string => $this->nullableString($code, 255),
                    $trackingCodes,
                ))),
                $positions,
                $this->decimal($entry['shippingGross'] ?? null),
                $this->snapshot($entry['shippingAddress'] ?? []),
                $entry,
            );
        }
    }

    /**
     * @param array<string, mixed> $customerPayload
     * @param array<string, mixed> $billingAddress
     * @param array<string, mixed> $shippingAddress
     */
    private function upsertCustomerProfile(
        IntegrationConnection $connection,
        array $customerPayload,
        EntityManagerInterface $entityManager,
    ): Customer {
        $externalId = $this->nullableString($customerPayload['externalId'] ?? null, 128);
        if ($externalId === null) {
            throw new \InvalidArgumentException('An imported customer requires an external ID.');
        }

        $customer = $entityManager->getRepository(Customer::class)->findOneBy([
            'connection' => $connection,
            'externalId' => $externalId,
        ]);
        if (!$customer instanceof Customer) {
            $customer = new Customer($connection->getTenant(), $connection, $externalId);
            $entityManager->persist($customer);
        }
        $customer->updateProfile($this->customerProfile($customerPayload));

        return $customer;
    }

    /** @param array<string, mixed> $snapshot */
    private function resolveOrderCustomer(
        IntegrationConnection $connection,
        array $snapshot,
        EntityManagerInterface $entityManager,
    ): ?Customer {
        $externalId = $this->nullableString($snapshot['externalId'] ?? null, 128);
        if ($externalId === null || ($snapshot['guest'] ?? false) === true) {
            return null;
        }

        $customer = $entityManager->getRepository(Customer::class)->findOneBy([
            'connection' => $connection,
            'externalId' => $externalId,
        ]);
        if ($customer instanceof Customer) {
            return $customer;
        }

        $customer = new Customer($connection->getTenant(), $connection, $externalId);
        $customer->seedFromOrder([
            'email' => $this->nullableString($snapshot['email'] ?? null, 255),
            'firstName' => $this->nullableString($snapshot['firstName'] ?? null, 128),
            'lastName' => $this->nullableString($snapshot['lastName'] ?? null, 128),
            'company' => $this->nullableString($snapshot['company'] ?? null, 255),
            'phone' => $this->nullableString($snapshot['phone'] ?? null, 64),
        ]);
        $entityManager->persist($customer);

        return $customer;
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function customerProfile(array $payload): array
    {
        $vatIds = $payload['vatIds'] ?? [];
        if (!is_array($vatIds) || !array_is_list($vatIds)) {
            throw new \InvalidArgumentException('Invalid customer VAT IDs.');
        }

        return [
            'externalId' => $this->nullableString($payload['externalId'] ?? null, 128),
            'email' => $this->nullableString($payload['email'] ?? null, 255),
            'firstName' => $this->nullableString($payload['firstName'] ?? null, 128),
            'lastName' => $this->nullableString($payload['lastName'] ?? null, 128),
            'company' => $this->nullableString($payload['company'] ?? null, 255),
            'phone' => $this->nullableString($payload['phone'] ?? null, 64),
            'guest' => (bool) ($payload['guest'] ?? false),
            'customerNumber' => $this->nullableString($payload['customerNumber'] ?? null, 255),
            'accountType' => $this->nullableString($payload['accountType'] ?? null, 32),
            'title' => $this->nullableString($payload['title'] ?? null, 128),
            'active' => isset($payload['active']) ? (bool) $payload['active'] : null,
            'vatIds' => array_values(array_filter(array_map(
                fn (mixed $vatId): ?string => $this->nullableString($vatId, 64),
                $vatIds,
            ))),
            'defaultBillingAddressId' => $this->nullableString($payload['defaultBillingAddressId'] ?? null, 128),
            'defaultShippingAddressId' => $this->nullableString($payload['defaultShippingAddressId'] ?? null, 128),
            'languageExternalId' => $this->nullableString($payload['languageExternalId'] ?? null, 128),
            'groupExternalId' => $this->nullableString($payload['groupExternalId'] ?? null, 128),
            'salesChannelExternalId' => $this->nullableString($payload['salesChannelExternalId'] ?? null, 128),
            'customFields' => $this->snapshot($payload['customFields'] ?? []),
            'affiliateCode' => $this->nullableString($payload['affiliateCode'] ?? null, 255),
            'campaignCode' => $this->nullableString($payload['campaignCode'] ?? null, 255),
            'sourceFields' => $this->snapshot($payload['sourceFields'] ?? []),
        ];
    }

    /** @param array<string, mixed> $addressPayload */
    private function upsertAddress(
        Customer $customer,
        array $addressPayload,
        bool $billingDefault,
        bool $shippingDefault,
        EntityManagerInterface $entityManager,
    ): void {
        if ($addressPayload === []) {
            return;
        }

        $externalId = $this->nullableString($addressPayload['externalId'] ?? null, 128);
        if ($externalId === null) {
            return;
        }
        $address = $externalId === null
            ? null
            : $entityManager->getRepository(CustomerAddress::class)->findOneBy([
                'customer' => $customer,
                'externalId' => $externalId,
            ]);
        if (!$address instanceof CustomerAddress) {
            $address = new CustomerAddress($customer, $externalId);
            $customer->addAddress($address);
            $entityManager->persist($address);
        }

        $address->update(
            [
                'externalId' => $externalId,
                'firstName' => $this->nullableString($addressPayload['firstName'] ?? null, 128),
                'lastName' => $this->nullableString($addressPayload['lastName'] ?? null, 128),
                'company' => $this->nullableString($addressPayload['company'] ?? null, 255),
                'department' => $this->nullableString($addressPayload['department'] ?? null, 255),
                'title' => $this->nullableString($addressPayload['title'] ?? null, 128),
                'street' => $this->nullableString($addressPayload['street'] ?? null, 255),
                'zipcode' => $this->nullableString($addressPayload['zipcode'] ?? null, 32),
                'city' => $this->nullableString($addressPayload['city'] ?? null, 255),
                'countryCode' => $this->nullableString($addressPayload['countryCode'] ?? null, 2),
                'countryState' => $this->nullableString($addressPayload['countryState'] ?? null, 64),
                'phone' => $this->nullableString($addressPayload['phone'] ?? null, 64),
                'additionalAddressLine1' => $this->nullableString($addressPayload['additionalAddressLine1'] ?? null, 255),
                'additionalAddressLine2' => $this->nullableString($addressPayload['additionalAddressLine2'] ?? null, 255),
                'customFields' => $this->snapshot($addressPayload['customFields'] ?? []),
                'sourceFields' => $this->snapshot($addressPayload['sourceFields'] ?? []),
            ],
            $billingDefault,
            $shippingDefault,
        );
    }

    /** @return array<string, mixed> */
    private function snapshot(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Invalid channel order snapshot.');
        }

        return $value;
    }

    private function nullableString(mixed $value, int $maximumLength): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Invalid channel order value.');
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maximumLength) {
            throw new \InvalidArgumentException('Channel order value is too long.');
        }

        return $value;
    }

    private function decimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('Invalid channel order amount.');
        }

        return number_format((float) $value, 4, '.', '');
    }

    private function assertChannelConnection(IntegrationConnection $connection): void
    {
        if (
            !$connection->isEnabled()
            || $connection->getStatus() !== 'active'
            || !in_array('channel', $connection->getDirections(), true)
        ) {
            throw new \DomainException('The integration connection cannot receive channel orders.');
        }
    }

    private function orderedAt(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Invalid order date.');
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Invalid order date.');
        }
    }

    private function quantity(mixed $value): string
    {
        if (!is_numeric($value) || (float) $value <= 0) {
            throw new \InvalidArgumentException('Order quantity must be positive.');
        }

        return number_format((float) $value, 4, '.', '');
    }
}
