<?php

namespace App\Controller\Api;

use App\Entity\Customer;
use App\Entity\SalesOrder;
use App\Entity\SalesOrderAllocation;
use App\Entity\SalesOrderItem;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Service\SalesOrderIngestionService;
use App\Service\SalesPickListService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/sales')]
final class SalesController extends AbstractController
{
    #[Route('/picklists', methods: ['GET'])]
    public function picklists(
        Request $request,
        EntityManagerInterface $entityManager,
        SalesPickListService $pickLists,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(max(1, $request->query->getInt('limit', 25)), 100);
        $search = mb_strtolower(trim((string) $request->query->get('search', '')));
        $hasReservation = sprintf(
            'EXISTS (SELECT pickAllocation.id FROM %s pickAllocation '
            .'JOIN pickAllocation.salesOrderItem pickItem '
            .'WHERE pickItem.salesOrder = salesOrder AND pickAllocation.status = :allocationStatus)',
            SalesOrderAllocation::class,
        );

        $query = $entityManager->createQueryBuilder()
            ->select('salesOrder')
            ->from(SalesOrder::class, 'salesOrder')
            ->where('salesOrder.tenant = :tenant')
            ->andWhere('salesOrder.status IN (:statuses)')
            ->andWhere($hasReservation)
            ->setParameter('tenant', $tenant)
            ->setParameter('statuses', ['reserved', 'partially_fulfilled'])
            ->setParameter('allocationStatus', 'reserved');
        $totalQuery = $entityManager->createQueryBuilder()
            ->select('COUNT(salesOrder.id)')
            ->from(SalesOrder::class, 'salesOrder')
            ->where('salesOrder.tenant = :tenant')
            ->andWhere('salesOrder.status IN (:statuses)')
            ->andWhere($hasReservation)
            ->setParameter('tenant', $tenant)
            ->setParameter('statuses', ['reserved', 'partially_fulfilled'])
            ->setParameter('allocationStatus', 'reserved');

        if ($search !== '') {
            $query->andWhere('LOWER(salesOrder.externalNumber) LIKE :search')
                ->setParameter('search', '%'.$search.'%');
            $totalQuery->andWhere('LOWER(salesOrder.externalNumber) LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        $orders = $query
            ->orderBy('salesOrder.orderedAt', 'ASC')
            ->addOrderBy('salesOrder.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        if ($orders !== []) {
            $entityManager->createQueryBuilder()
                ->select('salesOrder', 'item', 'allocation', 'warehouse')
                ->from(SalesOrder::class, 'salesOrder')
                ->innerJoin('salesOrder.items', 'item')
                ->innerJoin('item.allocations', 'allocation')
                ->innerJoin('allocation.warehouse', 'warehouse')
                ->where('salesOrder IN (:orders)')
                ->setParameter('orders', $orders)
                ->getQuery()
                ->getResult();
        }

        return $this->json([
            'picklists' => array_map(static function (SalesOrder $order) use ($pickLists): array {
                $lines = $pickLists->lines($order);

                return [
                    'orderId' => $order->getId()->toRfc4122(),
                    'number' => $order->getExternalNumber(),
                    'status' => $order->getStatus(),
                    'orderedAt' => $order->getOrderedAt()?->format(DATE_ATOM),
                    'warehouse' => implode(', ', array_values(array_unique(array_column($lines, 'warehouse')))),
                    'lineCount' => count($lines),
                ];
            }, $orders),
            'pagination' => $this->pagination(
                $page,
                $limit,
                (int) $totalQuery->getQuery()->getSingleScalarResult(),
            ),
        ]);
    }

    #[Route('/orders', methods: ['GET'])]
    public function orders(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(max(1, $request->query->getInt('limit', 25)), 100);
        $search = mb_strtolower(trim((string) $request->query->get('search', '')));
        $status = trim((string) $request->query->get('status', ''));
        $sort = (string) $request->query->get('sort', 'orderedAt');
        $direction = strtoupper((string) $request->query->get('direction', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $sortField = match ($sort) {
            'externalNumber' => 'salesOrder.externalNumber',
            'status' => 'salesOrder.status',
            default => 'salesOrder.orderedAt',
        };

        $query = $entityManager->createQueryBuilder()
            ->select('salesOrder', 'customer', 'connection')
            ->from(SalesOrder::class, 'salesOrder')
            ->leftJoin('salesOrder.customer', 'customer')
            ->innerJoin('salesOrder.connection', 'connection')
            ->where('salesOrder.tenant = :tenant')
            ->setParameter('tenant', $tenant);
        $totalQuery = $entityManager->createQueryBuilder()
            ->select('COUNT(salesOrder.id)')
            ->from(SalesOrder::class, 'salesOrder')
            ->leftJoin('salesOrder.customer', 'customer')
            ->where('salesOrder.tenant = :tenant')
            ->setParameter('tenant', $tenant);
        if ($status !== '') {
            $query->andWhere('salesOrder.status = :status')->setParameter('status', $status);
            $totalQuery->andWhere('salesOrder.status = :status')->setParameter('status', $status);
        }
        if ($search !== '') {
            $condition = 'LOWER(salesOrder.externalNumber) LIKE :search OR LOWER(customer.email) LIKE :search OR LOWER(customer.firstName) LIKE :search OR LOWER(customer.lastName) LIKE :search';
            $query->andWhere($condition)->setParameter('search', '%'.$search.'%');
            $totalQuery->andWhere($condition)->setParameter('search', '%'.$search.'%');
        }

        $orders = $query
            ->orderBy($sortField, $direction)
            ->addOrderBy('salesOrder.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        $unresolvedCounts = $this->unresolvedCounts($orders, $entityManager);

        return $this->json([
            'orders' => array_map(
                fn (SalesOrder $order): array => $this->orderPayload(
                    $order,
                    $unresolvedCounts[$order->getId()->toRfc4122()] ?? 0,
                ),
                $orders,
            ),
            'pagination' => $this->pagination($page, $limit, (int) $totalQuery->getQuery()->getSingleScalarResult()),
        ]);
    }

    #[Route('/customers', methods: ['GET'])]
    public function customers(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(max(1, $request->query->getInt('limit', 25)), 100);
        $search = mb_strtolower(trim((string) $request->query->get('search', '')));
        $query = $entityManager->createQueryBuilder()
            ->select('customer', 'connection')
            ->from(Customer::class, 'customer')
            ->leftJoin('customer.connection', 'connection')
            ->where('customer.tenant = :tenant')
            ->setParameter('tenant', $tenant);
        $totalQuery = $entityManager->createQueryBuilder()
            ->select('COUNT(customer.id)')
            ->from(Customer::class, 'customer')
            ->where('customer.tenant = :tenant')
            ->setParameter('tenant', $tenant);
        if ($search !== '') {
            $condition = 'LOWER(customer.email) LIKE :search OR LOWER(customer.firstName) LIKE :search OR LOWER(customer.lastName) LIKE :search OR LOWER(customer.company) LIKE :search OR LOWER(customer.customerNumber) LIKE :search';
            $query->andWhere($condition)->setParameter('search', '%'.$search.'%');
            $totalQuery->andWhere($condition)->setParameter('search', '%'.$search.'%');
        }
        $customers = $query
            ->orderBy('customer.updatedAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'customers' => array_map($this->customerPayload(...), $customers),
            'pagination' => $this->pagination($page, $limit, (int) $totalQuery->getQuery()->getSingleScalarResult()),
        ]);
    }

    #[Route('/orders/{id}', methods: ['GET'])]
    public function order(
        string $id,
        EntityManagerInterface $entityManager,
        SalesPickListService $pickLists,
    ): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $order = $entityManager->getRepository(SalesOrder::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);
        if (!$order instanceof SalesOrder) {
            throw $this->createNotFoundException();
        }

        return $this->json(['order' => [
            ...$this->orderPayload($order),
            'customerId' => $order->getCustomer()?->getId()->toRfc4122(),
            'primaryPaymentExternalId' => $order->getSourcePayload()['primaryOrderTransactionId'] ?? null,
            'primaryDeliveryExternalId' => $order->getSourcePayload()['primaryOrderDeliveryId'] ?? null,
            'customerSnapshot' => $order->getCustomerSnapshot(),
            'customFields' => $order->getSourcePayload()['customFields'] ?? [],
            'sourceFields' => $order->getSourcePayload()['sourceFields'] ?? [],
            'billingAddress' => $order->getBillingAddressSnapshot(),
            'shippingAddress' => $order->getShippingAddressSnapshot(),
            'totals' => [
                'taxStatus' => $order->getTaxStatus(),
                'amountNet' => $order->getAmountNet(),
                'amountTax' => $order->getAmountTax(),
                'amountGross' => $order->getAmountGross(),
                'shippingNet' => $order->getShippingNet(),
                'shippingTax' => $order->getShippingTax(),
                'shippingGross' => $order->getShippingGross(),
            ],
            'unresolvedSkus' => $order->getUnresolvedSkus(),
            'shipmentReconciliationRequired' => $order->needsShipmentReconciliation(),
            'manualShipmentTargets' => $order->getManualShipmentTargets(),
            'pickList' => $pickLists->lines($order),
            'items' => array_map(static fn ($item) => [
                'id' => $item->getId()->toRfc4122(),
                'externalLineId' => $item->getExternalLineId(),
                'sku' => $item->getSku(),
                'type' => $item->getLineType(),
                'name' => $item->getName(),
                'quantity' => $item->getQuantity(),
                'productResolved' => $item->isProductResolved(),
                'reservedQuantity' => $item->getReservedQuantity(),
                'fulfilledQuantity' => $item->getFulfilledQuantity(),
                'unitGross' => $item->getUnitGross(),
                'totalTax' => $item->getTotalTax(),
                'totalGross' => $item->getTotalGross(),
                'sourceFields' => $item->getSourcePayload()['sourceFields'] ?? [],
            ], $order->getItems()->toArray()),
            'payments' => array_map(static fn ($payment) => [
                'externalId' => $payment->getExternalId(),
                'methodExternalId' => $payment->getMethodExternalId(),
                'method' => $payment->getMethodName(),
                'state' => $payment->getState(),
                'reference' => $payment->getReference(),
                'amount' => $payment->getAmount(),
                'currency' => $payment->getCurrencyCode(),
                'sourceFields' => $payment->getSourcePayload()['sourceFields'] ?? [],
            ], $order->getPayments()->toArray()),
            'deliveries' => array_map(static fn ($delivery) => [
                'externalId' => $delivery->getExternalId(),
                'methodExternalId' => $delivery->getMethodExternalId(),
                'method' => $delivery->getMethodName(),
                'state' => $delivery->getState(),
                'trackingNumber' => $delivery->getTrackingNumber(),
                'trackingCodes' => $delivery->getTrackingCodes(),
                'positions' => $delivery->getPositionsSnapshot(),
                'shippingGross' => $delivery->getShippingGross(),
                'shippingAddress' => $delivery->getShippingAddressSnapshot(),
                'sourceFields' => $delivery->getSourcePayload()['sourceFields'] ?? [],
            ], $order->getDeliveries()->toArray()),
        ]]);
    }

    #[Route('/orders/{id}/shipment-reconciliation', methods: ['POST'])]
    public function reconcileShipment(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        SalesOrderIngestionService $orders,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager);
        $user = $this->getUser();
        $membership = $entityManager->getRepository(TenantMembership::class)->findOneBy(['user' => $user]);
        if (!$membership instanceof TenantMembership || $membership->getRole() !== 'owner') {
            throw $this->createAccessDeniedException();
        }
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $order = $entityManager->getRepository(SalesOrder::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);
        if (!$order instanceof SalesOrder) {
            throw $this->createNotFoundException();
        }
        try {
            $payload = $request->toArray();
            $targets = $payload['targets'] ?? null;
            if (!is_array($targets) || array_is_list($targets)) {
                throw new \InvalidArgumentException('Provide shipped quantities by order line ID.');
            }
            $orders->reconcileManualShipment($order, $targets, $entityManager, $user);
        } catch (\JsonException|\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'status' => $order->getStatus(),
            'manualShipmentTargets' => $order->getManualShipmentTargets(),
        ]);
    }

    #[Route('/customers/{id}', methods: ['GET'])]
    public function customer(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $customer = $entityManager->getRepository(Customer::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);
        if (!$customer instanceof Customer) {
            throw $this->createNotFoundException();
        }
        $orders = $entityManager->getRepository(SalesOrder::class)->findBy(
            ['tenant' => $tenant, 'customer' => $customer],
            ['orderedAt' => 'DESC'],
            25,
        );
        $unresolvedCounts = $this->unresolvedCounts($orders, $entityManager);

        return $this->json(['customer' => [
            ...$this->customerPayload($customer),
            'affiliateCode' => $customer->getSourcePayload()['affiliateCode'] ?? null,
            'campaignCode' => $customer->getSourcePayload()['campaignCode'] ?? null,
            'customFields' => $customer->getSourcePayload()['customFields'] ?? [],
            'sourceFields' => $customer->getSourcePayload()['sourceFields'] ?? [],
            'addresses' => $customer->isProfileImported() ? array_map(static fn ($address) => [
                'id' => $address->getId()->toRfc4122(),
                'name' => trim($address->getFirstName().' '.$address->getLastName()),
                'title' => $address->getTitle(),
                'company' => $address->getCompany(),
                'department' => $address->getDepartment(),
                'street' => $address->getStreet(),
                'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
                'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
                'zipcode' => $address->getZipcode(),
                'city' => $address->getCity(),
                'countryCode' => $address->getCountryCode(),
                'countryState' => $address->getCountryState(),
                'phone' => $address->getPhone(),
                'billingDefault' => $address->isBillingDefault(),
                'shippingDefault' => $address->isShippingDefault(),
                'customFields' => $address->getSourcePayload()['customFields'] ?? [],
                'sourceFields' => $address->getSourcePayload()['sourceFields'] ?? [],
            ], $customer->getAddresses()->toArray()) : [],
            'orders' => array_map(
                fn (SalesOrder $order): array => $this->orderPayload(
                    $order,
                    $unresolvedCounts[$order->getId()->toRfc4122()] ?? 0,
                ),
                $orders,
            ),
        ]]);
    }

    /** @return array<string, int|bool> */
    private function pagination(int $page, int $limit, int $total): array
    {
        return ['page' => $page, 'limit' => $limit, 'total' => $total, 'hasMore' => $page * $limit < $total];
    }

    /** @return array<string, mixed> */
    private function orderPayload(SalesOrder $order, ?int $unresolvedCount = null): array
    {
        $customer = $order->getCustomer();

        return [
            'id' => $order->getId()->toRfc4122(),
            'number' => $order->getExternalNumber(),
            'status' => $order->getStatus(),
            'sourceStatus' => $order->getSourceStatus(),
            'unresolvedProductCount' => $unresolvedCount ?? count($order->getUnresolvedSkus()),
            'customer' => $customer instanceof Customer ? trim($customer->getFirstName().' '.$customer->getLastName()) ?: $customer->getEmail() : ($order->getCustomerSnapshot()['email'] ?? '—'),
            'currency' => $order->getCurrencyCode(),
            'orderedAt' => $order->getOrderedAt()?->format(DATE_ATOM),
            'connection' => $order->getConnection()->getName(),
        ];
    }

    /** @param list<SalesOrder> $orders @return array<string, int> */
    private function unresolvedCounts(array $orders, EntityManagerInterface $entityManager): array
    {
        if ($orders === []) {
            return [];
        }

        $rows = $entityManager->createQueryBuilder()
            ->select('IDENTITY(item.salesOrder) AS orderId', 'COUNT(DISTINCT COALESCE(item.sku, item.externalLineId)) AS itemCount')
            ->from(SalesOrderItem::class, 'item')
            ->where('item.salesOrder IN (:orders)')
            ->andWhere('item.product IS NULL')
            ->andWhere('item.lineType = :productType')
            ->groupBy('item.salesOrder')
            ->setParameter('orders', $orders)
            ->setParameter('productType', 'product')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $orderId = $row['orderId'] ?? null;
            if ($orderId instanceof Uuid) {
                $orderId = $orderId->toRfc4122();
            }
            if (is_string($orderId)) {
                $counts[$orderId] = (int) $row['itemCount'];
            }
        }

        return $counts;
    }

    /** @return array<string, mixed> */
    private function customerPayload(Customer $customer): array
    {
        return [
            'id' => $customer->getId()->toRfc4122(),
            'name' => trim($customer->getFirstName().' '.$customer->getLastName()) ?: $customer->getCompany() ?: '—',
            'email' => $customer->getEmail(),
            'phone' => $customer->getPhone(),
            'guest' => $customer->isGuest(),
            'customerNumber' => $customer->getCustomerNumber(),
            'accountType' => $customer->getAccountType(),
            'title' => $customer->getTitle(),
            'active' => $customer->isActive(),
            'vatIds' => $customer->getVatIds(),
            'profileImported' => $customer->isProfileImported(),
            'connection' => $customer->getConnection()?->getName(),
            'updatedAt' => $customer->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function tenant(EntityManagerInterface $entityManager): Tenant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $membership = $entityManager->getRepository(TenantMembership::class)->findOneBy(['user' => $user]);
        if (!$membership instanceof TenantMembership) {
            throw $this->createAccessDeniedException();
        }

        return $membership->getTenant();
    }
}
