<?php

namespace App\Controller\Api;

use App\Entity\Currency;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\SupplierInvoice;
use App\Entity\SupplierInvoiceItem;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/inventory/supplier-invoices')]
final class SupplierInvoiceController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 25)));
        $search = mb_strtolower(trim((string) $request->query->get('search', '')));
        $query = $entityManager->createQueryBuilder()
            ->select('invoice', 'supplier', 'po')
            ->from(SupplierInvoice::class, 'invoice')
            ->join('invoice.supplier', 'supplier')
            ->join('invoice.purchaseOrder', 'po')
            ->where('invoice.tenant = :tenant')
            ->setParameter('tenant', $tenant);
        $count = $entityManager->createQueryBuilder()
            ->select('COUNT(invoice.id)')
            ->from(SupplierInvoice::class, 'invoice')
            ->join('invoice.supplier', 'supplier')
            ->where('invoice.tenant = :tenant')
            ->setParameter('tenant', $tenant);
        if ($search !== '') {
            $condition = 'LOWER(invoice.invoiceNumber) LIKE :search OR LOWER(supplier.name) LIKE :search';
            $query->andWhere($condition)->setParameter('search', '%'.$search.'%');
            $count->andWhere($condition)->setParameter('search', '%'.$search.'%');
        }
        $total = (int) $count->getQuery()->getSingleScalarResult();
        $invoices = $query
            ->orderBy('invoice.createdAt', 'DESC')
            ->addOrderBy('invoice.id', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'items' => array_map(fn (SupplierInvoice $invoice) => $this->payload($invoice, false), $invoices),
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'hasMore' => $page * $limit < $total],
        ]);
    }

    #[Route('/orders', methods: ['GET'])]
    public function orders(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 25)));
        $search = mb_strtolower(trim((string) $request->query->get('search', '')));
        $query = $entityManager->createQueryBuilder()
            ->select('po', 'supplier')
            ->from(PurchaseOrder::class, 'po')
            ->join('po.supplier', 'supplier')
            ->where('po.tenant = :tenant')
            ->andWhere('po.status IN (:statuses)')
            ->setParameter('tenant', $tenant)
            ->setParameter('statuses', ['sent', 'partially_received', 'received']);
        if ($search !== '') {
            $query->andWhere('LOWER(supplier.name) LIKE :search OR LOWER(supplier.code) LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }
        $orders = $query
            ->orderBy('po.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();

        return $this->json([
            'items' => array_map(fn (PurchaseOrder $po) => [
                'id' => $po->getId()->toRfc4122(),
                'reference' => $po->getReference(),
                'supplierName' => $po->getSupplier()->getName(),
                'currency' => $po->getCurrency(),
            ], array_slice($orders, 0, $limit)),
            'hasMore' => count($orders) > $limit,
        ]);
    }

    #[Route('/orders/{id}/availability', methods: ['GET'])]
    public function availability(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $po = $this->order($id, $this->tenant($entityManager), $entityManager);
        $this->loadOrderItems($po, $entityManager);

        return $this->json(['order' => $this->orderAvailability($po, $entityManager)]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $invoice = $this->invoice($id, $this->tenant($entityManager), $entityManager);
        $this->loadInvoiceLines($invoice, $entityManager);

        return $this->json(['invoice' => $this->payload($invoice, true)]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $data = $request->toArray();
        $po = $this->order((string) ($data['purchaseOrderId'] ?? ''), $tenant, $entityManager);
        if (!in_array($po->getStatus(), ['sent', 'partially_received', 'received'], true)) {
            return $this->problem('Choose a sent purchase order.');
        }
        $invoice = new SupplierInvoice($tenant, $po);
        $problem = $this->fill($invoice, $data, $entityManager);
        if ($problem !== null) {
            return $this->problem($problem);
        }
        if ($this->duplicate($invoice, $entityManager)) {
            return $this->problem('This supplier invoice number already exists for that year.', Response::HTTP_CONFLICT);
        }
        $entityManager->persist($invoice);
        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->problem('This supplier invoice number already exists for that year.', Response::HTTP_CONFLICT);
        }

        return $this->json(['invoice' => $this->payload($invoice, true)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(string $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $invoice = $this->invoice($id, $this->tenant($entityManager, true), $entityManager);
        if (!in_array($invoice->getStatus(), ['draft', 'disputed'], true)) {
            return $this->problem('Only draft or disputed invoices can be edited.');
        }
        $data = $request->toArray();
        $number = trim((string) ($data['invoiceNumber'] ?? ''));
        $dateInput = (string) ($data['invoiceDate'] ?? '');
        $year = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput) ? (int) substr($dateInput, 0, 4) : 0;
        if ($number !== '' && $year > 0) {
            $existing = $entityManager->getRepository(SupplierInvoice::class)->findOneBy([
                'tenant' => $invoice->getTenant(),
                'supplier' => $invoice->getSupplier(),
                'invoiceYear' => $year,
                'invoiceNumber' => $number,
            ]);
            if ($existing instanceof SupplierInvoice && !$existing->getId()->equals($invoice->getId())) {
                return $this->problem('This supplier invoice number already exists for that year.', Response::HTTP_CONFLICT);
            }
        }
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $entityManager->lock($invoice->getPurchaseOrder(), LockMode::PESSIMISTIC_WRITE);
            $entityManager->refresh($invoice);
            $this->loadInvoiceLines($invoice, $entityManager);
            if (!in_array($invoice->getStatus(), ['draft', 'disputed'], true)) {
                $connection->rollBack();

                return $this->problem('Invoice status changed. Reload before editing.', Response::HTTP_CONFLICT);
            }
            $problem = $this->fill($invoice, $data, $entityManager);
            if ($problem !== null) {
                $connection->rollBack();

                return $this->problem($problem);
            }
            $entityManager->flush();
            $connection->commit();
        } catch (UniqueConstraintViolationException) {
            $connection->rollBack();

            return $this->problem('This supplier invoice number already exists for that year.', Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['invoice' => $this->payload($invoice, true)]);
    }

    #[Route('/{id}/match', methods: ['POST'])]
    public function match(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $invoice = $this->invoice($id, $this->tenant($entityManager, true), $entityManager);
        if (!in_array($invoice->getStatus(), ['draft', 'disputed'], true)) {
            return $this->problem('Only draft or disputed invoices can be matched.');
        }
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $po = $invoice->getPurchaseOrder();
            $entityManager->lock($po, LockMode::PESSIMISTIC_WRITE);
            $entityManager->refresh($invoice);
            $this->loadInvoiceLines($invoice, $entityManager);
            if (!in_array($invoice->getStatus(), ['draft', 'disputed'], true)) {
                $connection->rollBack();

                return $this->problem('Invoice status changed. Reload before matching.', Response::HTTP_CONFLICT);
            }
            $issues = $this->matchIssues($invoice, $entityManager);
            $invoice->recordMatch($issues);
            $entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['invoice' => $this->payload($invoice, true)]);
    }

    #[Route('/{id}/void', methods: ['POST'])]
    public function voidInvoice(string $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $invoice = $this->invoice($id, $this->tenant($entityManager, true), $entityManager);
        $reason = trim((string) ($request->toArray()['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 2000 || $invoice->getStatus() === 'void') {
            return $this->problem('Provide a reason for voiding an active invoice.');
        }
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $entityManager->lock($invoice->getPurchaseOrder(), LockMode::PESSIMISTIC_WRITE);
            $entityManager->refresh($invoice);
            $this->loadInvoiceLines($invoice, $entityManager);
            if ($invoice->getStatus() === 'void') {
                $connection->rollBack();

                return $this->problem('Invoice is already void.', Response::HTTP_CONFLICT);
            }
            $invoice->void($reason);
            $entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['invoice' => $this->payload($invoice, true)]);
    }

    private function fill(SupplierInvoice $invoice, array $data, EntityManagerInterface $entityManager): ?string
    {
        $number = trim((string) ($data['invoiceNumber'] ?? ''));
        $dateInput = $data['invoiceDate'] ?? null;
        $date = is_string($dateInput) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput)
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $dateInput)
            : false;
        if ($number === '' || mb_strlen($number) > 100 || !$date instanceof \DateTimeImmutable || $date->format('Y-m-d') !== $dateInput) {
            return 'Provide a supplier invoice number and valid invoice date.';
        }
        foreach (['tax', 'shipping', 'discount', 'declaredTotal'] as $field) {
            if (!$this->validDecimal($data[$field] ?? null, true)) {
                return 'Amounts must be non-negative numbers with at most four decimals.';
            }
        }
        $rows = $data['items'] ?? null;
        if (!is_array($rows) || $rows === [] || count($rows) > 200) {
            return 'Add at least one invoice line.';
        }
        $orderItems = [];
        foreach ($invoice->getPurchaseOrder()->getItems() as $item) {
            $orderItems[$item->getId()->toRfc4122()] = $item;
        }
        $seen = [];
        $parsed = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                return 'Invalid invoice line.';
            }
            $id = (string) ($row['purchaseOrderItemId'] ?? '');
            if (!isset($orderItems[$id]) || isset($seen[$id])
                || !$this->validDecimal($row['quantity'] ?? null, false)
                || !$this->validDecimal($row['unitCost'] ?? null, true)) {
                return 'Invoice lines must reference unique PO items with valid quantities and costs.';
            }
            $seen[$id] = true;
            $parsed[] = [$orderItems[$id], $this->decimal($row['quantity']), $this->decimal($row['unitCost'])];
        }
        $note = trim((string) ($data['note'] ?? ''));
        if (mb_strlen($note) > 5000) {
            return 'Invoice note is too long.';
        }
        $invoice->updateDraft(
            $number,
            $date,
            $this->decimal($data['tax']),
            $this->decimal($data['shipping']),
            $this->decimal($data['discount']),
            $this->decimal($data['declaredTotal']),
            $note === '' ? null : $note,
        );
        $existingLines = [];
        foreach ($invoice->getItems() as $line) {
            $existingLines[$line->getPurchaseOrderItem()->getId()->toRfc4122()] = $line;
        }
        foreach ($parsed as [$item, $quantity, $unitCost]) {
            $id = $item->getId()->toRfc4122();
            if (isset($existingLines[$id])) {
                $existingLines[$id]->update($quantity, $unitCost);
                unset($existingLines[$id]);
            } else {
                $invoice->addItem($item, $quantity, $unitCost);
            }
        }
        foreach ($existingLines as $line) {
            $invoice->removeItem($line);
        }

        return null;
    }

    private function matchIssues(SupplierInvoice $invoice, EntityManagerInterface $entityManager): array
    {
        $po = $invoice->getPurchaseOrder();
        $issues = [];
        if (!in_array($po->getStatus(), ['sent', 'partially_received', 'received'], true)) {
            $issues[] = 'Purchase order is not active.';
        }
        $billed = $this->matchedQuantities($po, $entityManager);
        $currency = $entityManager->getRepository(Currency::class)->findOneBy(['code' => $invoice->getCurrency()]);
        $precision = $currency instanceof Currency ? min(4, max(0, $currency->getDecimalPrecision())) : 2;
        $total = 0.0;
        foreach ($invoice->getItems() as $line) {
            $item = $line->getPurchaseOrderItem();
            $id = $item->getId()->toRfc4122();
            $product = $item->getProduct()->getSku() ?: $id;
            if ((float) $line->getQuantity() + ($billed[$id] ?? 0.0) > (float) $item->getReceivedQuantity() + 0.00001) {
                $issues[] = $product.': invoiced quantity exceeds received good quantity.';
            }
            if (abs((float) $line->getUnitCost() - (float) $item->getUnitCost()) > 0.00001) {
                $issues[] = $product.': unit cost differs from the PO.';
            }
            $total += round((float) $line->getQuantity() * (float) $line->getUnitCost(), $precision);
        }
        $total = round($total + (float) $invoice->getTax() + (float) $invoice->getShipping() - (float) $invoice->getDiscount(), $precision);
        if ($total < 0 || abs($total - round((float) $invoice->getDeclaredTotal(), $precision)) > 0.00001) {
            $issues[] = 'Declared total differs from the calculated invoice total.';
        }

        return $issues;
    }

    private function matchedQuantities(PurchaseOrder $po, EntityManagerInterface $entityManager): array
    {
        $rows = $entityManager->createQueryBuilder()
            ->select('IDENTITY(line.purchaseOrderItem) AS itemId', 'SUM(line.quantity) AS billed')
            ->from(SupplierInvoiceItem::class, 'line')
            ->join('line.invoice', 'invoice')
            ->where('invoice.purchaseOrder = :po')
            ->andWhere('invoice.status = :status')
            ->groupBy('line.purchaseOrderItem')
            ->setParameter('po', $po)
            ->setParameter('status', 'matched')
            ->getQuery()
            ->getArrayResult();
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['itemId']] = (float) $row['billed'];
        }

        return $result;
    }

    private function orderAvailability(PurchaseOrder $po, EntityManagerInterface $entityManager): array
    {
        $billed = $this->matchedQuantities($po, $entityManager);

        return [
            'id' => $po->getId()->toRfc4122(),
            'reference' => $po->getReference(),
            'supplierName' => $po->getSupplier()->getName(),
            'currency' => $po->getCurrency(),
            'items' => array_map(fn (PurchaseOrderItem $item) => [
                'id' => $item->getId()->toRfc4122(),
                'sku' => $item->getProduct()->getSku(),
                'purchaseUnit' => $item->getPurchaseUnit(),
                'ordered' => (float) $item->getQuantity(),
                'receivedGood' => (float) $item->getReceivedQuantity(),
                'receivedDamaged' => (float) $item->getDamagedQuantity(),
                'matchedBilled' => $billed[$item->getId()->toRfc4122()] ?? 0.0,
                'availableToInvoice' => max(0, (float) $item->getReceivedQuantity() - ($billed[$item->getId()->toRfc4122()] ?? 0.0)),
                'poUnitCost' => (float) $item->getUnitCost(),
            ], $po->getItems()->toArray()),
        ];
    }

    private function loadOrderItems(PurchaseOrder $po, EntityManagerInterface $entityManager): void
    {
        $entityManager->createQueryBuilder()
            ->select('po', 'item', 'product')
            ->from(PurchaseOrder::class, 'po')
            ->leftJoin('po.items', 'item')
            ->leftJoin('item.product', 'product')
            ->where('po = :po')
            ->setParameter('po', $po)
            ->getQuery()
            ->getResult();
    }

    private function loadInvoiceLines(SupplierInvoice $invoice, EntityManagerInterface $entityManager): void
    {
        $entityManager->createQueryBuilder()
            ->select('invoice', 'line', 'orderItem', 'product')
            ->from(SupplierInvoice::class, 'invoice')
            ->leftJoin('invoice.items', 'line')
            ->leftJoin('line.purchaseOrderItem', 'orderItem')
            ->leftJoin('orderItem.product', 'product')
            ->where('invoice = :invoice')
            ->setParameter('invoice', $invoice)
            ->getQuery()
            ->getResult();
    }

    private function payload(SupplierInvoice $invoice, bool $details): array
    {
        $po = $invoice->getPurchaseOrder();
        $result = [
            'id' => $invoice->getId()->toRfc4122(),
            'invoiceNumber' => $invoice->getInvoiceNumber(),
            'invoiceDate' => $invoice->getInvoiceDate()->format('Y-m-d'),
            'status' => $invoice->getStatus(),
            'supplierName' => $invoice->getSupplier()->getName(),
            'purchaseOrderId' => $po->getId()->toRfc4122(),
            'purchaseOrderReference' => $po->getReference(),
            'currency' => $invoice->getCurrency(),
            'declaredTotal' => (float) $invoice->getDeclaredTotal(),
            'createdAt' => $invoice->getCreatedAt()->format(DATE_ATOM),
        ];
        if ($details) {
            $result += [
                'tax' => (float) $invoice->getTax(),
                'shipping' => (float) $invoice->getShipping(),
                'discount' => (float) $invoice->getDiscount(),
                'note' => $invoice->getNote(),
                'matchIssues' => $invoice->getMatchIssues(),
                'matchedAt' => $invoice->getMatchedAt()?->format(DATE_ATOM),
                'voidedAt' => $invoice->getVoidedAt()?->format(DATE_ATOM),
                'voidReason' => $invoice->getVoidReason(),
                'items' => array_map(fn (SupplierInvoiceItem $item) => [
                    'purchaseOrderItemId' => $item->getPurchaseOrderItem()->getId()->toRfc4122(),
                    'sku' => $item->getPurchaseOrderItem()->getProduct()->getSku(),
                    'quantity' => (float) $item->getQuantity(),
                    'unitCost' => (float) $item->getUnitCost(),
                ], $invoice->getItems()->toArray()),
            ];
        }

        return $result;
    }

    private function duplicate(SupplierInvoice $invoice, EntityManagerInterface $entityManager): bool
    {
        $existing = $entityManager->getRepository(SupplierInvoice::class)->findOneBy([
            'tenant' => $invoice->getTenant(),
            'supplier' => $invoice->getSupplier(),
            'invoiceYear' => $invoice->getInvoiceYear(),
            'invoiceNumber' => $invoice->getInvoiceNumber(),
        ]);

        return $existing instanceof SupplierInvoice && !$existing->getId()->equals($invoice->getId());
    }

    private function order(string $id, Tenant $tenant, EntityManagerInterface $entityManager): PurchaseOrder
    {
        $order = Uuid::isValid($id)
            ? $entityManager->getRepository(PurchaseOrder::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant])
            : null;
        if (!$order instanceof PurchaseOrder) {
            throw $this->createNotFoundException();
        }

        return $order;
    }

    private function invoice(string $id, Tenant $tenant, EntityManagerInterface $entityManager): SupplierInvoice
    {
        $invoice = Uuid::isValid($id)
            ? $entityManager->getRepository(SupplierInvoice::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant])
            : null;
        if (!$invoice instanceof SupplierInvoice) {
            throw $this->createNotFoundException();
        }

        return $invoice;
    }

    private function tenant(EntityManagerInterface $entityManager, bool $owner = false): Tenant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $membership = $entityManager->getRepository(TenantMembership::class)->findOneBy(['user' => $user]);
        if (!$membership instanceof TenantMembership || ($owner && $membership->getRole() !== 'owner')) {
            throw $this->createAccessDeniedException();
        }

        return $membership->getTenant();
    }

    private function validDecimal(mixed $value, bool $allowZero): bool
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return false;
        }
        if (!preg_match('/^\d{1,15}(?:\.\d{1,4})?$/', trim((string) $value))) {
            return false;
        }

        return $allowZero || (float) $value > 0;
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }

    private function problem(string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return $this->json(['message' => $message], $status);
    }
}
