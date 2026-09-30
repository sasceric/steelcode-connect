<?php

namespace App\Controller\Api;

use App\Entity\InventoryLevel;
use App\Entity\Locale;
use App\Entity\Product;
use App\Entity\ProductTranslation;
use App\Entity\PurchaseOrder;
use App\Entity\PurchaseOrderItem;
use App\Entity\SupplierOffer;
use App\Entity\SupplierOfferPrice;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Service\InventoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/inventory/replenishment')]
final class ReplenishmentController extends AbstractController
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    #[Route('', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 25)));
        $suggestionsOnly = $request->query->getBoolean('suggestions', false);
        $search = mb_strtolower(trim((string) $request->query->get('search', '')));
        $warehouseId = (string) $request->query->get('warehouseId', '');
        $query = $entityManager->createQueryBuilder()
            ->select('level', 'product', 'warehouse')
            ->from(InventoryLevel::class, 'level')
            ->join('level.product', 'product')
            ->join('level.warehouse', 'warehouse')
            ->where('level.tenant = :tenant')
            ->andWhere('level.reorderThreshold IS NOT NULL')
            ->setParameter('tenant', $tenant);
        $count = $entityManager->createQueryBuilder()
            ->select('COUNT(level.id)')
            ->from(InventoryLevel::class, 'level')
            ->join('level.product', 'product')
            ->join('level.warehouse', 'warehouse')
            ->where('level.tenant = :tenant')
            ->andWhere('level.reorderThreshold IS NOT NULL')
            ->setParameter('tenant', $tenant);
        if ($suggestionsOnly) {
            $condition = '(level.quantity - level.reservedQuantity - level.unavailableQuantity + level.incomingQuantity) <= level.reorderThreshold';
            $query->andWhere($condition);
            $count->andWhere($condition);
        }
        if (Uuid::isValid($warehouseId)) {
            $warehouse = $this->warehouse($warehouseId, $tenant, $entityManager);
            $query->andWhere('level.warehouse = :warehouse')->setParameter('warehouse', $warehouse);
            $count->andWhere('level.warehouse = :warehouse')->setParameter('warehouse', $warehouse);
        }
        if ($search !== '') {
            $condition = 'LOWER(COALESCE(product.sku, \'\')) LIKE :search OR EXISTS ('
                .'SELECT 1 FROM '.ProductTranslation::class.' translation '
                .'WHERE translation.product = product AND LOWER(translation.name) LIKE :search)';
            $query->andWhere($condition)->setParameter('search', '%'.$search.'%');
            $count->andWhere($condition)->setParameter('search', '%'.$search.'%');
        }
        $total = (int) $count->getQuery()->getSingleScalarResult();
        $levels = $query
            ->orderBy('warehouse.name', 'ASC')
            ->addOrderBy('product.sku', 'ASC')
            ->addOrderBy('level.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        $products = array_map(fn (InventoryLevel $level) => $level->getProduct(), $levels);
        $offers = $this->preferredOffers($products, $tenant, $entityManager);
        $names = $this->productNames($products, $tenant, $entityManager);

        return $this->json([
            'items' => array_map(
                fn (InventoryLevel $level) => $this->policyPayload(
                    $level,
                    $names[$level->getProduct()->getId()->toRfc4122()] ?? null,
                    $offers[$level->getProduct()->getId()->toRfc4122()] ?? null,
                ),
                $levels,
            ),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'hasMore' => $page * $limit < $total,
            ],
        ]);
    }

    #[Route('/policies', methods: ['POST'])]
    public function savePolicy(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $data = $request->toArray();
        $warehouse = $this->warehouse((string) ($data['warehouseId'] ?? ''), $tenant, $entityManager);
        $product = $this->product((string) ($data['productId'] ?? ''), $tenant, $entityManager);
        $threshold = $data['threshold'] ?? null;
        $target = $data['target'] ?? null;
        $leadDays = $data['leadDays'] ?? null;
        if (!$warehouse->isActive()
            || !$this->validDecimal($threshold, true)
            || !$this->validDecimal($target, false)
            || (float) $target <= (float) $threshold
            || ($leadDays !== null && (!ctype_digit((string) $leadDays) || (int) $leadDays > 3650))
        ) {
            return $this->problem('Choose an active warehouse, a non-negative reorder point, a higher target, and a valid lead time.');
        }
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $this->inventory->lockProduct($tenant, $warehouse, $product, $entityManager);
            $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
                'tenant' => $tenant,
                'warehouse' => $warehouse,
                'product' => $product,
            ]);
            if (!$level instanceof InventoryLevel) {
                $level = new InventoryLevel($tenant, $warehouse, $product);
                $entityManager->persist($level);
            }
            $level->setReorderPolicy(
                $this->decimal($threshold),
                $this->decimal($target),
                $leadDays === null ? null : (int) $leadDays,
            );
            $entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['policy' => $this->policyPayload($level)]);
    }

    #[Route('/policies/{id}', methods: ['DELETE'])]
    public function removePolicy(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $level = $this->level($id, $tenant, $entityManager);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $this->inventory->lockProduct($tenant, $level->getWarehouse(), $level->getProduct(), $entityManager);
            $entityManager->refresh($level);
            $level->setReorderPolicy(null, null, null);
            $entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['removed' => true]);
    }

    #[Route('/drafts', methods: ['POST'])]
    public function createDrafts(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $data = $request->toArray();
        $ids = $data['policyIds'] ?? null;
        if (!is_array($ids) || $ids === [] || count($ids) > 100 || count(array_unique($ids)) !== count($ids)) {
            return $this->problem('Select between 1 and 100 unique replenishment policies.');
        }
        foreach ($ids as $id) {
            if (!is_string($id) || !Uuid::isValid($id)) {
                return $this->problem('Invalid replenishment policy selection.');
            }
        }
        sort($ids, SORT_STRING);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $orders = [];
            foreach ($ids as $id) {
                $level = $this->level($id, $tenant, $entityManager);
                $warehouse = $level->getWarehouse();
                $product = $level->getProduct();
                $this->inventory->lockProduct($tenant, $warehouse, $product, $entityManager);
                $entityManager->refresh($level);
                if (!$warehouse->isActive() || $level->getReorderThreshold() === null || $level->getReorderTarget() === null) {
                    throw new \DomainException('A selected replenishment policy is no longer active.');
                }
                $position = (float) $level->getAvailableQuantity() + (float) $level->getIncomingQuantity();
                if ($position > (float) $level->getReorderThreshold() || $position >= (float) $level->getReorderTarget()) {
                    throw new \DomainException('A selected product no longer needs replenishment.');
                }
                $offer = $entityManager->getRepository(SupplierOffer::class)->findOneBy([
                    'tenant' => $tenant,
                    'product' => $product,
                    'preferred' => true,
                    'active' => true,
                ]);
                if (!$offer instanceof SupplierOffer || !$offer->getSupplier()->isActive()) {
                    throw new \DomainException('Every selected product needs a current preferred supplier offer.');
                }
                $purchaseQuantity = $this->suggestedQuantity($level, $offer);
                $price = $this->priceForSuggestion($offer, $purchaseQuantity);
                if (!$price instanceof SupplierOfferPrice) {
                    throw new \DomainException('Every selected product needs a current price in its preferred currency.');
                }
                $existingDrafts = (int) $entityManager->createQueryBuilder()
                    ->select('COUNT(item.id)')
                    ->from(PurchaseOrderItem::class, 'item')
                    ->join('item.purchaseOrder', 'po')
                    ->where('po.tenant = :tenant')
                    ->andWhere('po.warehouse = :warehouse')
                    ->andWhere('po.status = :status')
                    ->andWhere('item.product = :product')
                    ->setParameter('tenant', $tenant)
                    ->setParameter('warehouse', $warehouse)
                    ->setParameter('status', 'draft')
                    ->setParameter('product', $product)
                    ->getQuery()
                    ->getSingleScalarResult();
                if ($existingDrafts > 0) {
                    throw new \DomainException('A draft PO already contains one of the selected products for this warehouse.');
                }
                $group = implode(':', [
                    $warehouse->getId()->toRfc4122(),
                    $offer->getSupplier()->getId()->toRfc4122(),
                    $offer->getCurrency(),
                ]);
                if (!isset($orders[$group])) {
                    $orders[$group] = new PurchaseOrder(
                        $tenant,
                        $offer->getSupplier(),
                        $warehouse,
                        $offer->getCurrency(),
                        'Generated from replenishment policies. Review before sending.',
                    );
                    $entityManager->persist($orders[$group]);
                }
                $orders[$group]->addItem(
                    $product,
                    $purchaseQuantity,
                    $price->getUnitCost(),
                    $offer->getSupplierSku(),
                    $offer->getPurchaseUnit(),
                    $offer->getStockUnitsPerPurchaseUnit(),
                );
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

        return $this->json(['orders' => array_map(
            fn (PurchaseOrder $order) => [
                'id' => $order->getId()->toRfc4122(),
                'reference' => $order->getReference(),
                'supplierName' => $order->getSupplier()->getName(),
                'warehouseName' => $order->getWarehouse()->getName(),
                'items' => $order->getItems()->count(),
            ],
            array_values($orders),
        )], Response::HTTP_CREATED);
    }

    private function preferredOffers(array $products, Tenant $tenant, EntityManagerInterface $entityManager): array
    {
        if ($products === []) {
            return [];
        }
        $rows = $entityManager->createQueryBuilder()
            ->select('offer', 'supplier')
            ->from(SupplierOffer::class, 'offer')
            ->join('offer.supplier', 'supplier')
            ->where('offer.tenant = :tenant')
            ->andWhere('offer.product IN (:products)')
            ->andWhere('offer.preferred = true')
            ->andWhere('offer.active = true')
            ->andWhere('supplier.active = true')
            ->setParameter('tenant', $tenant)
            ->setParameter('products', $products)
            ->getQuery()
            ->getResult();
        $offers = [];
        if ($rows !== []) {
            $entityManager->createQueryBuilder()
                ->select('offer', 'price')
                ->from(SupplierOffer::class, 'offer')
                ->leftJoin('offer.prices', 'price')
                ->where('offer IN (:offers)')
                ->setParameter('offers', $rows)
                ->getQuery()
                ->getResult();
        }
        foreach ($rows as $offer) {
            if ($this->priceForSuggestion($offer, $offer->getMinimumOrderQuantity()) instanceof SupplierOfferPrice) {
                $offers[$offer->getProduct()->getId()->toRfc4122()] = $offer;
            }
        }

        return $offers;
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
        $names = [];
        foreach ($entityManager->getRepository(ProductTranslation::class)->findBy([
            'locale' => $locale,
            'product' => $products,
        ]) as $translation) {
            $names[$translation->getProduct()->getId()->toRfc4122()] = $translation->getName();
        }

        return $names;
    }

    private function policyPayload(InventoryLevel $level, ?string $name = null, ?SupplierOffer $offer = null): array
    {
        $position = (float) $level->getAvailableQuantity() + (float) $level->getIncomingQuantity();
        $needsOrder = $level->getReorderThreshold() !== null
            && $level->getReorderTarget() !== null
            && $position <= (float) $level->getReorderThreshold()
            && $position < (float) $level->getReorderTarget();
        $quantity = $needsOrder && $offer instanceof SupplierOffer
            ? $this->suggestedQuantity($level, $offer)
            : null;
        $price = $quantity !== null && $offer instanceof SupplierOffer
            ? $this->priceForSuggestion($offer, $quantity)
            : null;

        return [
            'id' => $level->getId()->toRfc4122(),
            'productId' => $level->getProduct()->getId()->toRfc4122(),
            'productName' => $name,
            'productSku' => $level->getProduct()->getSku(),
            'warehouseId' => $level->getWarehouse()->getId()->toRfc4122(),
            'warehouseName' => $level->getWarehouse()->getName(),
            'available' => (float) $level->getAvailableQuantity(),
            'incoming' => (float) $level->getIncomingQuantity(),
            'position' => $position,
            'threshold' => $level->getReorderThreshold() === null ? null : (float) $level->getReorderThreshold(),
            'target' => $level->getReorderTarget() === null ? null : (float) $level->getReorderTarget(),
            'leadDays' => $level->getReorderLeadDays(),
            'needsOrder' => $needsOrder,
            'supplierName' => $offer?->getSupplier()->getName(),
            'offerId' => $offer?->getId()->toRfc4122(),
            'purchaseUnit' => $offer?->getPurchaseUnit(),
            'stockUnitsPerPurchaseUnit' => $offer?->getStockUnitsPerPurchaseUnit(),
            'suggestedPurchaseQuantity' => $quantity === null ? null : (float) $quantity,
            'currency' => $price?->getCurrency() ?? $offer?->getCurrency(),
            'estimatedCost' => $quantity === null || $price === null ? null : (float) $quantity * (float) $price->getUnitCost(),
            'expectedDays' => $level->getReorderLeadDays() ?? $offer?->getLeadTimeDays(),
        ];
    }

    private function suggestedQuantity(InventoryLevel $level, SupplierOffer $offer): string
    {
        $position = (float) $level->getAvailableQuantity() + (float) $level->getIncomingQuantity();
        $shortage = max(0, (float) $level->getReorderTarget() - $position);
        $purchaseUnits = ceil($shortage / $offer->getStockUnitsPerPurchaseUnit());

        $minimumPriceQuantity = null;
        foreach ($offer->getPrices() as $price) {
            if ($price->getCurrency() !== $offer->getCurrency() || !$price->isValidOn(new \DateTimeImmutable('today'))) {
                continue;
            }
            $minimumPriceQuantity = $minimumPriceQuantity === null
                ? (float) $price->getMinimumQuantity()
                : min($minimumPriceQuantity, (float) $price->getMinimumQuantity());
        }

        return $this->decimal(max($purchaseUnits, (float) $offer->getMinimumOrderQuantity(), $minimumPriceQuantity ?? 0));
    }

    private function priceForSuggestion(SupplierOffer $offer, string $quantity): ?SupplierOfferPrice
    {
        $price = $offer->priceFor($quantity, $offer->getCurrency());
        if ($price instanceof SupplierOfferPrice) {
            return $price;
        }

        foreach ($offer->getPrices() as $candidate) {
            if ($candidate->getCurrency() === $offer->getCurrency()
                && $candidate->isValidOn(new \DateTimeImmutable('today'))) {
                return $candidate;
            }
        }

        return null;
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

    private function level(string $id, Tenant $tenant, EntityManagerInterface $entityManager): InventoryLevel
    {
        $level = Uuid::isValid($id) ? $entityManager->getRepository(InventoryLevel::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]) : null;
        if (!$level instanceof InventoryLevel) {
            throw $this->createNotFoundException();
        }

        return $level;
    }

    private function warehouse(string $id, Tenant $tenant, EntityManagerInterface $entityManager): Warehouse
    {
        $warehouse = Uuid::isValid($id) ? $entityManager->getRepository(Warehouse::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]) : null;
        if (!$warehouse instanceof Warehouse) {
            throw $this->createNotFoundException();
        }

        return $warehouse;
    }

    private function product(string $id, Tenant $tenant, EntityManagerInterface $entityManager): Product
    {
        $product = Uuid::isValid($id) ? $entityManager->getRepository(Product::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]) : null;
        if (!$product instanceof Product) {
            throw $this->createNotFoundException();
        }

        return $product;
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
