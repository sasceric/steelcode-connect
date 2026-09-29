<?php

namespace App\Controller\Api;

use App\Entity\Product;
use App\Entity\ProductTranslation;
use App\Entity\Locale;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\PurchaseReceipt;
use App\Entity\Address;
use App\Entity\Supplier;
use App\Entity\SupplierOffer;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Service\InventoryService;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/api/v1/inventory')]
final class PurchasingController extends AbstractController
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    #[Route('/supplier-offers', methods: ['GET'])]
    public function offers(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 25)));
        $query = $entityManager->createQueryBuilder()
            ->select('offer', 'supplier', 'product')
            ->from(SupplierOffer::class, 'offer')
            ->join('offer.supplier', 'supplier')
            ->join('offer.product', 'product')
            ->where('offer.tenant = :tenant')
            ->setParameter('tenant', $tenant);
        $countQuery = $entityManager->createQueryBuilder()
            ->select('COUNT(offer.id)')
            ->from(SupplierOffer::class, 'offer')
            ->where('offer.tenant = :tenant')
            ->setParameter('tenant', $tenant);
        $supplierId = (string) $request->query->get('supplierId', '');
        $activeOnly = $request->query->getBoolean('active', false);
        if ($activeOnly) {
            $validity = 'offer.active = true AND (offer.validFrom IS NULL OR offer.validFrom <= :today) AND (offer.validUntil IS NULL OR offer.validUntil >= :today)';
            $query->andWhere($validity)->setParameter('today', new \DateTimeImmutable('today'));
            $countQuery->andWhere($validity)->setParameter('today', new \DateTimeImmutable('today'));
        }
        if (Uuid::isValid($supplierId)) {
            $supplier = $this->findSupplier($supplierId, $tenant, $entityManager);
            $query->andWhere('offer.supplier = :supplier')->setParameter('supplier', $supplier);
            $countQuery->andWhere('offer.supplier = :supplier')->setParameter('supplier', $supplier);
        }
        $search = mb_strtolower(trim((string) $request->query->get('search', '')));
        if ($search !== '') {
            $condition = 'LOWER(COALESCE(product.sku, \'\')) LIKE :search'
                .' OR LOWER(COALESCE(offer.supplierSku, \'\')) LIKE :search'
                .' OR EXISTS (SELECT 1 FROM '.ProductTranslation::class.' translation WHERE translation.product = product AND LOWER(translation.name) LIKE :search)';
            $query->andWhere($condition)->setParameter('search', '%'.$search.'%');
            $countQuery->join('offer.product', 'product')->andWhere($condition)->setParameter('search', '%'.$search.'%');
        }
        $total = (int) $countQuery->getQuery()->getSingleScalarResult();
        $offers = $query
            ->orderBy('offer.updatedAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        $names = $this->productNames(
            array_map(fn (SupplierOffer $offer) => $offer->getProduct(), $offers),
            $tenant,
            $entityManager,
        );

        return $this->json([
            'offers' => array_map(
                fn (SupplierOffer $offer) => $this->offerPayload(
                    $offer,
                    $names[$offer->getProduct()->getId()->toRfc4122()] ?? null,
                ),
                $offers,
            ),
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'hasMore' => $page * $limit < $total],
        ]);
    }

    #[Route('/supplier-offers', methods: ['POST'])]
    public function createOffer(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $data = $request->toArray();
        $supplier = $this->findSupplier((string) ($data['supplierId'] ?? ''), $tenant, $entityManager);
        $product = $this->findProduct((string) ($data['productId'] ?? ''), $tenant, $entityManager);
        if (!$supplier->isActive()) {
            return $this->problem('Choose an active supplier.');
        }
        $existing = $entityManager->getRepository(SupplierOffer::class)->findOneBy([
            'tenant' => $tenant,
            'supplier' => $supplier,
            'product' => $product,
        ]);
        if ($existing instanceof SupplierOffer) {
            return $this->problem('An offer for this supplier and product already exists.', Response::HTTP_CONFLICT);
        }
        $offer = new SupplierOffer($tenant, $supplier, $product);
        $error = $this->updateOfferValues($offer, $data);
        if ($error !== null) {
            return $this->problem($error);
        }
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            if ($offer->isActive() && $offer->isPreferred()) {
                $this->clearOtherPreferredOffers($offer, $tenant, $entityManager);
            }
            $entityManager->persist($offer);
            $entityManager->flush();
            $connection->commit();
        } catch (UniqueConstraintViolationException $exception) {
            $connection->rollBack();

            return $this->problem('Another offer already uses this product or preferred supplier selection.', Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['offer' => $this->offerPayload($offer)], Response::HTTP_CREATED);
    }

    #[Route('/supplier-offers/{id}', methods: ['PATCH'])]
    public function updateOffer(string $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $offer = $this->findOffer($id, $tenant, $entityManager);
        $data = $request->toArray();
        $error = $this->updateOfferValues($offer, $data);
        if ($error !== null) {
            return $this->problem($error);
        }
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            if ($offer->isActive() && $offer->isPreferred()) {
                $this->clearOtherPreferredOffers($offer, $tenant, $entityManager);
            }
            $entityManager->flush();
            $connection->commit();
        } catch (UniqueConstraintViolationException $exception) {
            $connection->rollBack();

            return $this->problem('Another active preferred offer was saved for this product. Reload and try again.', Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['offer' => $this->offerPayload($offer)]);
    }

    #[Route('/purchase-orders', methods: ['GET'])]
    public function orders(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 25)));
        $total = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(po.id)')
            ->from(PurchaseOrder::class, 'po')
            ->where('po.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->getSingleScalarResult();
        $orders = $entityManager->createQueryBuilder()
            ->select('po', 'supplier', 'warehouse')
            ->from(PurchaseOrder::class, 'po')
            ->join('po.supplier', 'supplier')
            ->join('po.warehouse', 'warehouse')
            ->where('po.tenant = :tenant')
            ->setParameter('tenant', $tenant)
            ->orderBy('po.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        if ($orders !== []) {
            $entityManager->createQueryBuilder()
                ->select('po', 'items', 'product')
                ->from(PurchaseOrder::class, 'po')
                ->leftJoin('po.items', 'items')
                ->leftJoin('items.product', 'product')
                ->where('po IN (:orders)')
                ->setParameter('orders', $orders)
                ->getQuery()
                ->getResult();
        }

        return $this->json([
            'orders' => array_map($this->orderPayload(...), $orders),
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'hasMore' => $page * $limit < $total],
        ]);
    }

    #[Route('/purchase-orders', methods: ['POST'])]
    public function createOrder(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $data = $request->toArray();
        $supplier = $this->findSupplier((string) ($data['supplierId'] ?? ''), $tenant, $entityManager);
        $warehouse = $this->findWarehouse((string) ($data['warehouseId'] ?? ''), $tenant, $entityManager);
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'EUR')));
        $items = $data['items'] ?? [];
        if (!$supplier->isActive() || !$warehouse->isActive() || !preg_match('/^[A-Z]{3}$/', $currency) || !is_array($items) || $items === []) {
            return $this->problem('Choose an active supplier and warehouse, a currency, and at least one line.');
        }
        $order = new PurchaseOrder($tenant, $supplier, $warehouse, $currency, $this->nullable($data['note'] ?? null));
        try {
            $this->addOrderLines($order, $items, $supplier, $currency, $tenant, $entityManager);
        } catch (\DomainException $exception) {
            return $this->problem($exception->getMessage());
        }
        $entityManager->persist($order);
        $entityManager->flush();

        return $this->json(['order' => $this->orderPayload($order)], Response::HTTP_CREATED);
    }

    #[Route('/purchase-orders/{id}', methods: ['PATCH'])]
    public function updateOrder(string $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $order = $this->findOrder($id, $tenant, $entityManager);
        $data = $request->toArray();
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $entityManager->lock($order, LockMode::PESSIMISTIC_WRITE);
            $entityManager->refresh($order);
            $supplier = $this->findSupplier((string) ($data['supplierId'] ?? ''), $tenant, $entityManager);
            $warehouse = $this->findWarehouse((string) ($data['warehouseId'] ?? ''), $tenant, $entityManager);
            $currency = strtoupper(trim((string) ($data['currency'] ?? '')));
            $items = $data['items'] ?? null;
            if (!$supplier->isActive() || !$warehouse->isActive() || !preg_match('/^[A-Z]{3}$/', $currency) || !is_array($items) || $items === []) {
                throw new \DomainException('Choose an active supplier and warehouse, a currency, and at least one line.');
            }
            $order->updateDraft($supplier, $warehouse, $currency, $this->nullable($data['note'] ?? null));
            $entityManager->flush();
            $this->addOrderLines($order, $items, $supplier, $currency, $tenant, $entityManager);
            $entityManager->flush();
            $connection->commit();
        } catch (\DomainException $exception) {
            $connection->rollBack();

            return $this->problem($exception->getMessage(), Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['order' => $this->orderPayload($order)]);
    }

    private function addOrderLines(
        PurchaseOrder $order,
        array $items,
        Supplier $supplier,
        string $currency,
        Tenant $tenant,
        EntityManagerInterface $entityManager,
    ): void {
        $seen = [];
        foreach ($items as $line) {
            if (!is_array($line) || !$this->validDecimal($line['quantity'] ?? null, false)) {
                throw new \DomainException('Every line needs a positive quantity.');
            }
            $product = $this->findProduct((string) ($line['productId'] ?? ''), $tenant, $entityManager);
            $productId = $product->getId()->toRfc4122();
            if (isset($seen[$productId])) {
                throw new \DomainException('A product may appear only once in a purchase order.');
            }
            $seen[$productId] = true;
            $offer = $entityManager->getRepository(SupplierOffer::class)->findOneBy([
                'tenant' => $tenant,
                'supplier' => $supplier,
                'product' => $product,
                'active' => true,
            ]);
            if (!$offer instanceof SupplierOffer || $offer->getCurrency() !== $currency || !$offer->isCurrentlyValid()) {
                throw new \DomainException('Every ordered product needs a currently valid active offer in the order currency.');
            }
            if ((float) $line['quantity'] < (float) $offer->getMinimumQuantity()) {
                throw new \DomainException('An ordered quantity is below the supplier minimum.');
            }
            if ((float) $line['quantity'] * $offer->getStockUnitsPerPurchaseUnit() >= 1000000000000000) {
                throw new \DomainException('The converted stock quantity exceeds the supported range.');
            }
            $order->addItem(
                $product,
                $this->decimal($line['quantity']),
                $offer->getUnitCost(),
                $offer->getSupplierSku(),
                $offer->getPurchaseUnit(),
                $offer->getStockUnitsPerPurchaseUnit(),
            );
        }
    }

    #[Route('/purchase-orders/{id}', methods: ['GET'])]
    public function order(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $order = $this->findOrder($id, $tenant, $entityManager);
        $receipts = $entityManager->getRepository(PurchaseReceipt::class)->findBy(
            ['purchaseOrder' => $order],
            ['createdAt' => 'DESC'],
        );

        return $this->json([
            'order' => $this->orderPayload($order),
            'receipts' => array_map(
                fn (PurchaseReceipt $receipt) => [
                    'id' => $receipt->getId()->toRfc4122(),
                    'receiptKey' => $receipt->getReceiptKey()?->toRfc4122(),
                    'productId' => $receipt->getItem()->getProduct()->getId()->toRfc4122(),
                    'sku' => $receipt->getItem()->getProduct()->getSku(),
                    'goodQuantity' => (float) $receipt->getGoodQuantity(),
                    'damagedQuantity' => (float) $receipt->getDamagedQuantity(),
                    'note' => $receipt->getNote(),
                    'damageResolution' => $receipt->getDamageResolution(),
                    'damageResolutionNote' => $receipt->getDamageResolutionNote(),
                    'damageResolvedAt' => $receipt->getDamageResolvedAt()?->format(DATE_ATOM),
                    'damageHistory' => $receipt->getDamageHistory(),
                    'createdAt' => $receipt->getCreatedAt()->format(DATE_ATOM),
                ],
                $receipts,
            ),
        ]);
    }

    #[Route('/purchase-orders/{id}/pdf', methods: ['GET'])]
    public function orderPdf(string $id, EntityManagerInterface $entityManager): Response
    {
        $tenant = $this->tenant($entityManager);
        $order = $this->findOrder($id, $tenant, $entityManager);
        $supplier = $order->getSupplier();
        $destination = $order->getSupplierSnapshot() ?? [
            'name' => $supplier->getName(),
            'contactName' => $supplier->getContactName(),
            'street' => $supplier->getStreet(),
            'postalCode' => $supplier->getPostalCode(),
            'city' => $supplier->getCity(),
            'country' => $supplier->getCountry(),
            'email' => $supplier->getEmail(),
        ];
        $address = $entityManager->getRepository(Address::class)->findOneBy([
            'tenant' => $tenant,
            'isDefault' => true,
        ]);
        $escape = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $reference = 'PO-'.$order->getCreatedAt()->format('Ymd').'-'.strtoupper(substr(str_replace('-', '', $order->getId()->toRfc4122()), -8));
        $supplierLines = array_filter([
            $destination['name'] ?? null,
            $destination['contactName'] ?? null,
            $destination['street'] ?? null,
            trim((string) ($destination['postalCode'] ?? '').' '.(string) ($destination['city'] ?? '')),
            $destination['country'] ?? null,
            $destination['email'] ?? null,
        ]);
        $senderLines = array_filter([
            $tenant->getName(),
            $address instanceof Address ? $address->getStreet() : null,
            $address instanceof Address ? $address->getZipcode().' '.$address->getCity() : null,
            $address instanceof Address ? $address->getCountry() : null,
            $tenant->getEmail(),
        ]);
        $rows = '';
        $total = 0.0;
        $items = $order->getItems()->toArray();
        $names = $this->productNames(array_map(fn (PurchaseOrderItem $item) => $item->getProduct(), $items), $tenant, $entityManager);
        foreach ($items as $item) {
            $lineTotal = (float) $item->getQuantity() * (float) $item->getUnitCost();
            $total += $lineTotal;
            $productId = $item->getProduct()->getId()->toRfc4122();
            $rows .= '<tr><td>'.$escape($names[$productId] ?? $item->getProduct()->getSku()).'<br><small>'.$escape($item->getProduct()->getSku()).'</small></td>';
            $rows .= '<td>'.$escape($item->getSupplierSku()).'</td><td class="number">'.$escape($item->getQuantity()).' '.$escape($item->getPurchaseUnit()).'</td>';
            $rows .= '<td class="number">'.$escape($item->getStockUnitsPerPurchaseUnit()).'</td><td class="number">'.number_format((float) $item->getUnitCost(), 2).' '.$escape($order->getCurrency()).'</td>';
            $rows .= '<td class="number">'.number_format($lineTotal, 2).' '.$escape($order->getCurrency()).'</td></tr>';
        }
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;color:#222;font-size:11px}h1{font-size:23px;margin:0 0 14px}.columns{width:100%;margin-bottom:26px}.columns td{width:50%;vertical-align:top;line-height:1.6}.label{font-weight:bold;color:#666;text-transform:uppercase;font-size:9px}table.lines{width:100%;border-collapse:collapse}.lines th,.lines td{padding:8px 5px;border-bottom:1px solid #ddd;text-align:left}.lines th{background:#f3f4f6}.number{text-align:right!important}small{color:#777}.total{text-align:right;font-size:15px;margin-top:16px}.note{margin-top:25px;white-space:pre-wrap}</style></head><body>';
        $html .= '<h1>Purchase order '.$escape($reference).'</h1><p>Date: '.$escape($order->getCreatedAt()->format('Y-m-d')).' &nbsp; Status: '.$escape($order->getStatus()).'</p>';
        $html .= '<table class="columns"><tr><td><span class="label">From</span><br>'.implode('<br>', array_map($escape, $senderLines)).'</td><td><span class="label">Supplier</span><br>'.implode('<br>', array_map($escape, $supplierLines)).'</td></tr></table>';
        $html .= '<p><span class="label">Deliver to warehouse</span><br>'.$escape($order->getWarehouse()->getName()).'</p>';
        $html .= '<table class="lines"><thead><tr><th>Product</th><th>Supplier SKU</th><th class="number">Quantity</th><th class="number">Stock units/unit</th><th class="number">Unit cost</th><th class="number">Total</th></tr></thead><tbody>'.$rows.'</tbody></table>';
        $html .= '<p class="total"><strong>Total: '.number_format($total, 2).' '.$escape($order->getCurrency()).'</strong></p>';
        if ($order->getNote() !== null) {
            $html .= '<div class="note"><span class="label">Notes</span><br>'.$escape($order->getNote()).'</div>';
        }
        $html .= '</body></html>';
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4');
        $pdf->render();

        return new Response($pdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s.pdf"', $reference),
        ]);
    }

    #[Route('/purchase-orders/{id}/receipts/{receiptId}/damage', methods: ['PATCH'])]
    public function resolveReceiptDamage(
        string $id,
        string $receiptId,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
        $order = $this->findOrder($id, $tenant, $entityManager);
        $receipt = Uuid::isValid($receiptId)
            ? $entityManager->getRepository(PurchaseReceipt::class)->findOneBy([
                'id' => Uuid::fromString($receiptId),
                'purchaseOrder' => $order,
            ])
            : null;
        if (!$receipt instanceof PurchaseReceipt) {
            throw $this->createNotFoundException();
        }
        $data = $request->toArray();
        try {
            $receipt->resolveDamage(
                (string) ($data['resolution'] ?? ''),
                $this->nullable($data['note'] ?? null),
                $this->getUser() instanceof User ? $this->getUser() : null,
            );
        } catch (\DomainException $exception) {
            return $this->problem($exception->getMessage());
        }
        $entityManager->flush();

        return $this->json(['receipt' => [
            'id' => $receipt->getId()->toRfc4122(),
            'damageResolution' => $receipt->getDamageResolution(),
            'damageResolutionNote' => $receipt->getDamageResolutionNote(),
            'damageResolvedAt' => $receipt->getDamageResolvedAt()?->format(DATE_ATOM),
            'damageHistory' => $receipt->getDamageHistory(),
        ]]);
    }

    #[Route('/purchase-orders/{id}/send', methods: ['POST'])]
    public function sendOrder(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        return $this->changeOrder($id, 'send', null, $entityManager);
    }

    #[Route('/purchase-orders/{id}/email', methods: ['POST'])]
    public function emailOrder(
        string $id,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
        $order = $this->findOrder($id, $tenant, $entityManager);
        if (!in_array($order->getStatus(), ['sent', 'partially_received', 'received'], true)) {
            return $this->problem('Mark the order as sent before emailing it.');
        }
        $recipient = (string) ($order->getSupplierSnapshot()['email'] ?? '');
        $sender = (string) ($_ENV['BREVO_FROM'] ?? '');
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            return $this->problem('A valid supplier email and configured sender address are required.');
        }
        $reference = 'PO-'.$order->getCreatedAt()->format('Ymd').'-'.strtoupper(substr(str_replace('-', '', $order->getId()->toRfc4122()), -8));
        $pdf = $this->orderPdf($id, $entityManager)->getContent();
        $email = (new Email())
            ->from($sender)
            ->to($recipient)
            ->subject('Purchase order '.$reference.' from '.$tenant->getName())
            ->text("Please find our purchase order attached.\n\nReference: ".$reference."\n\n".$tenant->getName())
            ->attach($pdf, $reference.'.pdf', 'application/pdf');
        try {
            $mailer->send($email);
        } catch (\Throwable $exception) {
            return $this->problem('Email delivery failed; the order was not marked as emailed.', Response::HTTP_BAD_GATEWAY);
        }
        $order->recordEmail($recipient);
        $entityManager->flush();

        return $this->json(['order' => $this->orderPayload($order)]);
    }

    #[Route('/purchase-orders/{id}/cancel', methods: ['POST'])]
    public function cancelOrder(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        return $this->changeOrder($id, 'cancel', null, $entityManager);
    }

    #[Route('/purchase-orders/{id}/receive', methods: ['POST'])]
    public function receiveOrder(string $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        return $this->changeOrder($id, 'receive', $request->toArray(), $entityManager);
    }

    private function changeOrder(string $id, string $action, ?array $data, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $order = $this->findOrder($id, $tenant, $entityManager);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $entityManager->lock($order, LockMode::PESSIMISTIC_WRITE);
            $entityManager->refresh($order);
            if ($action === 'receive') {
                $receiptKey = (string) ($data['receiptKey'] ?? '');
                if (!Uuid::isValid($receiptKey)) {
                    throw new \DomainException('A valid receipt key is required.');
                }
                $existingReceipts = $entityManager->getRepository(PurchaseReceipt::class)->findBy([
                    'purchaseOrder' => $order,
                    'receiptKey' => Uuid::fromString($receiptKey),
                ]);
                if ($existingReceipts !== []) {
                    $this->assertMatchingReceiptReplay($existingReceipts, $data['items'] ?? null);
                    $connection->commit();

                    return $this->json(['order' => $this->orderPayload($order)]);
                }
            }
            $status = $order->getStatus();
            if ($action === 'send' && $status !== 'draft') {
                throw new \DomainException('Only draft orders can be sent.');
            }
            if ($action === 'receive' && !in_array($status, ['sent', 'partially_received'], true)) {
                throw new \DomainException('Only sent orders can be received.');
            }
            if ($action === 'cancel' && !in_array($status, ['draft', 'sent', 'partially_received'], true)) {
                throw new \DomainException('This order cannot be cancelled.');
            }
            if ($action === 'receive') {
                $this->applyReceipt($order, $data ?? [], $entityManager);
                $order->markReceived();
            } else {
                $items = $order->getItems()->toArray();
                usort(
                    $items,
                    fn (PurchaseOrderItem $a, PurchaseOrderItem $b) => strcmp(
                        $a->getProduct()->getId()->toRfc4122(),
                        $b->getProduct()->getId()->toRfc4122(),
                    ),
                );
                foreach ($items as $item) {
                    $this->inventory->lockProduct($tenant, $order->getWarehouse(), $item->getProduct(), $entityManager);
                    if ($action === 'send') {
                        $this->inventory->changeIncoming($tenant, $order->getWarehouse(), $item->getProduct(), $item->toStockQuantity($item->getQuantity()), $entityManager);
                    } elseif ($status !== 'draft' && (float) $item->getOpenQuantity() > 0) {
                        $this->inventory->changeIncoming($tenant, $order->getWarehouse(), $item->getProduct(), $this->decimal(-(float) $item->toStockQuantity($item->getOpenQuantity())), $entityManager);
                    }
                }
                if ($action === 'send') {
                    $order->markSent();
                } else {
                    $order->cancel();
                }
            }
            $entityManager->flush();
            $connection->commit();
        } catch (\DomainException $exception) {
            $connection->rollBack();

            return $this->problem($exception->getMessage(), Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['order' => $this->orderPayload($order)]);
    }

    private function applyReceipt(PurchaseOrder $order, array $data, EntityManagerInterface $entityManager): void
    {
        $receiptKey = Uuid::fromString((string) $data['receiptKey']);
        $lines = $data['items'] ?? null;
        if (!is_array($lines) || $lines === []) {
            throw new \DomainException('Select at least one line to receive.');
        }
        usort(
            $lines,
            fn (mixed $a, mixed $b) => strcmp(
                is_array($a) ? (string) ($a['itemId'] ?? '') : '',
                is_array($b) ? (string) ($b['itemId'] ?? '') : '',
            ),
        );
        $byId = [];
        foreach ($order->getItems() as $item) {
            $byId[$item->getId()->toRfc4122()] = $item;
        }
        $seen = [];
        foreach ($lines as $line) {
            if (!is_array($line)) {
                throw new \DomainException('Invalid receipt line.');
            }
            $itemId = (string) ($line['itemId'] ?? '');
            if (!isset($byId[$itemId]) || isset($seen[$itemId])) {
                throw new \DomainException('Receipt line is not part of this order or is duplicated.');
            }
            $seen[$itemId] = true;
            $good = $line['goodQuantity'] ?? 0;
            $damaged = $line['damagedQuantity'] ?? 0;
            if (!$this->validDecimal($good, true) || !$this->validDecimal($damaged, true) || (float) $good + (float) $damaged <= 0) {
                throw new \DomainException('Receipt quantities must be non-negative and at least one must be positive.');
            }
            $item = $byId[$itemId];
            if ((float) $good + (float) $damaged > (float) $item->getOpenQuantity() + 0.00001) {
                throw new \DomainException('Receipt exceeds the outstanding ordered quantity.');
            }
            $good = $this->decimal($good);
            $damaged = $this->decimal($damaged);
            $this->inventory->lockProduct($order->getTenant(), $order->getWarehouse(), $item->getProduct(), $entityManager);
            $this->inventory->receivePurchase(
                $order->getTenant(),
                $order->getWarehouse(),
                $item->getProduct(),
                $item->toStockQuantity($good),
                $item->toStockQuantity($damaged),
                $this->getUser() instanceof User ? $this->getUser() : null,
                $order->getId()->toRfc4122(),
                $this->nullable($data['note'] ?? null),
                $entityManager,
            );
            $item->receive($good, $damaged);
            $entityManager->persist(new PurchaseReceipt(
                $order,
                $receiptKey,
                $item,
                $good,
                $damaged,
                $this->getUser() instanceof User ? $this->getUser() : null,
                $this->nullable($data['note'] ?? null),
            ));
        }
    }

    private function assertMatchingReceiptReplay(array $receipts, mixed $lines): void
    {
        if (!is_array($lines) || count($lines) !== count($receipts)) {
            throw new \DomainException('Receipt key was already used for a different delivery.');
        }
        $expected = [];
        foreach ($receipts as $receipt) {
            $expected[$receipt->getItem()->getId()->toRfc4122()] = [
                $receipt->getGoodQuantity(),
                $receipt->getDamagedQuantity(),
            ];
        }
        $seen = [];
        foreach ($lines as $line) {
            if (!is_array($line) || !is_numeric($line['goodQuantity'] ?? null) || !is_numeric($line['damagedQuantity'] ?? null)) {
                throw new \DomainException('Receipt key was already used for a different delivery.');
            }
            $itemId = (string) ($line['itemId'] ?? '');
            if (isset($seen[$itemId]) || !isset($expected[$itemId]) || $expected[$itemId] !== [
                $this->decimal($line['goodQuantity']),
                $this->decimal($line['damagedQuantity']),
            ]) {
                throw new \DomainException('Receipt key was already used for a different delivery.');
            }
            $seen[$itemId] = true;
        }
    }

    private function updateOfferValues(SupplierOffer $offer, array $data): ?string
    {
        $unitCost = $data['unitCost'] ?? $offer->getUnitCost();
        $minimum = $data['minimumQuantity'] ?? $offer->getMinimumQuantity();
        $currency = strtoupper(trim((string) ($data['currency'] ?? $offer->getCurrency())));
        $leadDays = $data['leadTimeDays'] ?? $offer->getLeadTimeDays();
        $purchaseUnit = trim((string) ($data['purchaseUnit'] ?? $offer->getPurchaseUnit()));
        $stockUnitsPerPurchaseUnit = $data['stockUnitsPerPurchaseUnit'] ?? $offer->getStockUnitsPerPurchaseUnit();
        $validFromInput = array_key_exists('validFrom', $data) ? $data['validFrom'] : $offer->getValidFrom()?->format('Y-m-d');
        $validUntilInput = array_key_exists('validUntil', $data) ? $data['validUntil'] : $offer->getValidUntil()?->format('Y-m-d');
        if (!$this->validDecimal($unitCost, true) || !$this->validDecimal($minimum, false) || !preg_match('/^[A-Z]{3}$/', $currency)) {
            return 'Enter a non-negative cost, positive minimum quantity, and three-letter currency.';
        }
        if ($leadDays !== null && (!ctype_digit((string) $leadDays) || (int) $leadDays > 36500)) {
            return 'Lead time must be a non-negative number of days.';
        }
        if ($purchaseUnit === '' || mb_strlen($purchaseUnit) > 64 || !ctype_digit((string) $stockUnitsPerPurchaseUnit) || (int) $stockUnitsPerPurchaseUnit < 1 || (int) $stockUnitsPerPurchaseUnit > 1000000) {
            return 'Enter a purchase unit and a stock-unit conversion between 1 and 1000000.';
        }
        $validFrom = $this->parseDate($validFromInput);
        $validUntil = $this->parseDate($validUntilInput);
        if ($validFrom === false || $validUntil === false || ($validFrom !== null && $validUntil !== null && $validFrom > $validUntil)) {
            return 'Enter a valid offer date range.';
        }
        $offer->update(
            $this->nullable($data['supplierSku'] ?? $offer->getSupplierSku()),
            $this->decimal($unitCost),
            $currency,
            $this->decimal($minimum),
            $leadDays === null ? null : (int) $leadDays,
            (bool) ($data['preferred'] ?? $offer->isPreferred()),
            (bool) ($data['active'] ?? $offer->isActive()),
        );
        $offer->setPurchaseUnit($purchaseUnit, (int) $stockUnitsPerPurchaseUnit);
        $offer->setValidity($validFrom, $validUntil);

        return null;
    }

    private function clearOtherPreferredOffers(SupplierOffer $offer, Tenant $tenant, EntityManagerInterface $entityManager): void
    {
        $entityManager->getConnection()->executeStatement(
            'UPDATE supplier_offers SET preferred = false, updated_at = NOW() WHERE tenant_id = ? AND product_id = ? AND id <> ? AND preferred = true AND active = true',
            [
                $tenant->getId()->toRfc4122(),
                $offer->getProduct()->getId()->toRfc4122(),
                $offer->getId()->toRfc4122(),
            ],
        );
    }

    private function offerPayload(SupplierOffer $offer, ?string $productName = null): array
    {
        return [
            'id' => $offer->getId()->toRfc4122(),
            'supplierId' => $offer->getSupplier()->getId()->toRfc4122(),
            'supplierName' => $offer->getSupplier()->getName(),
            'productId' => $offer->getProduct()->getId()->toRfc4122(),
            'productSku' => $offer->getProduct()->getSku(),
            'productName' => $productName,
            'supplierSku' => $offer->getSupplierSku(),
            'unitCost' => (float) $offer->getUnitCost(),
            'currency' => $offer->getCurrency(),
            'minimumQuantity' => (float) $offer->getMinimumQuantity(),
            'purchaseUnit' => $offer->getPurchaseUnit(),
            'stockUnitsPerPurchaseUnit' => $offer->getStockUnitsPerPurchaseUnit(),
            'validFrom' => $offer->getValidFrom()?->format('Y-m-d'),
            'validUntil' => $offer->getValidUntil()?->format('Y-m-d'),
            'leadTimeDays' => $offer->getLeadTimeDays(),
            'preferred' => $offer->isPreferred(),
            'active' => $offer->isActive(),
        ];
    }

    private function productNames(array $products, Tenant $tenant, EntityManagerInterface $entityManager): array
    {
        if ($products === []) {
            return [];
        }
        $locale = $entityManager->getRepository(Locale::class)->findOneBy([
            'code' => $tenant->getDefaultSnippetLocale(),
            'active' => true,
        ]);
        if (!$locale instanceof Locale) {
            return [];
        }
        $translations = $entityManager->getRepository(ProductTranslation::class)->findBy([
            'locale' => $locale,
            'product' => $products,
        ]);
        $names = [];
        foreach ($translations as $translation) {
            $names[$translation->getProduct()->getId()->toRfc4122()] = $translation->getName();
        }

        return $names;
    }

    private function orderPayload(PurchaseOrder $order): array
    {
        return [
            'id' => $order->getId()->toRfc4122(),
            'supplierId' => $order->getSupplier()->getId()->toRfc4122(),
            'supplierName' => $order->getSupplier()->getName(),
            'warehouseId' => $order->getWarehouse()->getId()->toRfc4122(),
            'warehouseName' => $order->getWarehouse()->getName(),
            'status' => $order->getStatus(),
            'currency' => $order->getCurrency(),
            'note' => $order->getNote(),
            'supplierSnapshot' => $order->getSupplierSnapshot(),
            'createdAt' => $order->getCreatedAt()->format(DATE_ATOM),
            'sentAt' => $order->getSentAt()?->format(DATE_ATOM),
            'lastEmailedAt' => $order->getLastEmailedAt()?->format(DATE_ATOM),
            'lastEmailedTo' => $order->getLastEmailedTo(),
            'items' => array_map(
                fn (PurchaseOrderItem $item) => [
                    'id' => $item->getId()->toRfc4122(),
                    'productId' => $item->getProduct()->getId()->toRfc4122(),
                    'sku' => $item->getProduct()->getSku(),
                    'supplierSku' => $item->getSupplierSku(),
                    'quantity' => (float) $item->getQuantity(),
                    'receivedQuantity' => (float) $item->getReceivedQuantity(),
                    'damagedQuantity' => (float) $item->getDamagedQuantity(),
                    'openQuantity' => (float) $item->getOpenQuantity(),
                    'unitCost' => (float) $item->getUnitCost(),
                    'purchaseUnit' => $item->getPurchaseUnit(),
                    'stockUnitsPerPurchaseUnit' => $item->getStockUnitsPerPurchaseUnit(),
                ],
                $order->getItems()->toArray(),
            ),
        ];
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

    private function findSupplier(string $id, Tenant $tenant, EntityManagerInterface $entityManager): Supplier
    {
        $supplier = Uuid::isValid($id) ? $entityManager->getRepository(Supplier::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant]) : null;
        if (!$supplier instanceof Supplier) {
            throw $this->createNotFoundException();
        }

        return $supplier;
    }

    private function findProduct(string $id, Tenant $tenant, EntityManagerInterface $entityManager): Product
    {
        $product = Uuid::isValid($id) ? $entityManager->getRepository(Product::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant]) : null;
        if (!$product instanceof Product) {
            throw $this->createNotFoundException();
        }

        return $product;
    }

    private function findWarehouse(string $id, Tenant $tenant, EntityManagerInterface $entityManager): Warehouse
    {
        $warehouse = Uuid::isValid($id) ? $entityManager->getRepository(Warehouse::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant]) : null;
        if (!$warehouse instanceof Warehouse) {
            throw $this->createNotFoundException();
        }

        return $warehouse;
    }

    private function findOffer(string $id, Tenant $tenant, EntityManagerInterface $entityManager): SupplierOffer
    {
        $offer = Uuid::isValid($id) ? $entityManager->getRepository(SupplierOffer::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant]) : null;
        if (!$offer instanceof SupplierOffer) {
            throw $this->createNotFoundException();
        }

        return $offer;
    }

    private function findOrder(string $id, Tenant $tenant, EntityManagerInterface $entityManager): PurchaseOrder
    {
        $order = Uuid::isValid($id) ? $entityManager->getRepository(PurchaseOrder::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant]) : null;
        if (!$order instanceof PurchaseOrder) {
            throw $this->createNotFoundException();
        }

        return $order;
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 4, '.', '');
    }

    private function validDecimal(mixed $value, bool $allowZero): bool
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return false;
        }
        $value = trim((string) $value);
        if (!preg_match('/^\d{1,15}(?:\.\d{1,4})?$/', $value)) {
            return false;
        }

        return $allowZero || (float) $value > 0;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function parseDate(mixed $value): \DateTimeImmutable|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof \DateTimeImmutable && $date->format('Y-m-d') === $value ? $date : false;
    }

    private function problem(string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return $this->json(['message' => $message], $status);
    }
}
