<?php

namespace App\Controller\Api;

use App\Entity\InventoryLevel;
use App\Entity\InventoryMovement;
use App\Entity\InventoryCount;
use App\Entity\InventoryCountItem;
use App\Entity\InventoryTransfer;
use App\Entity\InventoryTransferItem;
use App\Entity\Locale;
use App\Entity\Product;
use App\Entity\ProductTranslation;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Entity\Warehouse;
use App\Service\InventoryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/inventory')]
final class InventoryController extends AbstractController
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    #[Route('/warehouses', methods: ['GET'])]
    public function warehouses(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $this->inventory->defaultWarehouse($tenant, $entityManager);
        $entityManager->flush();
        $limit = $request->query->getInt('limit');
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim((string) $request->query->get('search', ''));
        $sort = (string) $request->query->get('sort', 'name');
        $direction = strtoupper((string) $request->query->get('direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $sortField = match ($sort) {
            'name' => 'warehouse.name',
            'code' => 'warehouse.code',
            'active' => 'warehouse.active',
            'priority' => 'warehouse.priority',
            default => 'warehouse.name',
        };
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('warehouse')
            ->from(Warehouse::class, 'warehouse')
            ->where('warehouse.tenant = :tenant')
            ->setParameter('tenant', $tenant);
        if ($search !== '') {
            $queryBuilder
                ->andWhere('LOWER(warehouse.name) LIKE :search OR LOWER(warehouse.code) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }
        $queryBuilder->orderBy($sortField, $direction);
        $pageSize = $limit > 0 ? min($limit, 100) : null;
        if ($pageSize !== null) {
            $queryBuilder
                ->setMaxResults($pageSize)
                ->setFirstResult(($page - 1) * $pageSize);
        }
        $warehouses = $queryBuilder->getQuery()->getResult();
        $response = ['warehouses' => array_map($this->warehousePayload(...), $warehouses)];
        if ($pageSize !== null) {
            $totalQuery = $entityManager->createQueryBuilder()
                ->select('COUNT(warehouse.id)')
                ->from(Warehouse::class, 'warehouse')
                ->where('warehouse.tenant = :tenant')
                ->setParameter('tenant', $tenant);
            if ($search !== '') {
                $totalQuery
                    ->andWhere('LOWER(warehouse.name) LIKE :search OR LOWER(warehouse.code) LIKE :search')
                    ->setParameter('search', '%'.mb_strtolower($search).'%');
            }
            $total = (int) $totalQuery->getQuery()->getSingleScalarResult();
            $response['pagination'] = [
                'page' => $page,
                'limit' => $pageSize,
                'total' => $total,
                'hasMore' => $page * $pageSize < $total,
            ];
        }

        return $this->json($response);
    }

    #[Route('/warehouses', methods: ['POST'])]
    public function createWarehouse(Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $name = trim((string) ($data['name'] ?? ''));
        $code = $this->code((string) ($data['code'] ?? $name));
        if ($name === '' || $code === '') {
            return $this->validation(
                $translator,
                array_filter([
                    $name === '' ? 'name' : null,
                    $code === '' ? 'code' : null,
                ]),
                true,
            );
        }
        if ($entityManager->getRepository(Warehouse::class)->findOneBy(['tenant' => $tenant, 'code' => $code]) instanceof Warehouse) {
            return $this->validation($translator, ['code']);
        }
        $warehouse = new Warehouse(
            $tenant,
            $code,
            $name,
            (bool) ($data['fulfillmentEnabled'] ?? true),
            max(0, (int) ($data['priority'] ?? 0)),
        );
        $entityManager->persist($warehouse);
        $entityManager->flush();

        return $this->json(['warehouse' => $this->warehousePayload($warehouse)], Response::HTTP_CREATED);
    }

    #[Route('/warehouses/{id}', methods: ['PATCH'])]
    public function updateWarehouse(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $warehouse = $this->warehouse($id, $tenant, $entityManager);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $name = trim((string) ($data['name'] ?? $warehouse->getName()));
        $code = $this->code((string) ($data['code'] ?? $warehouse->getCode()));
        $duplicate = $entityManager->getRepository(Warehouse::class)->findOneBy(['tenant' => $tenant, 'code' => $code]);
        if ($name === '' || $code === '') {
            return $this->validation(
                $translator,
                array_filter([
                    $name === '' ? 'name' : null,
                    $code === '' ? 'code' : null,
                ]),
                true,
            );
        }
        if (
            ($duplicate instanceof Warehouse
                && $duplicate->getId()->toRfc4122() !== $warehouse->getId()->toRfc4122())
            || ($warehouse->getCode() === 'default' && $code !== 'default')
        ) {
            return $this->validation($translator, ['code']);
        }
        $isDefault = $warehouse->getCode() === 'default';
        $warehouse->update(
            $code,
            $name,
            $isDefault ? true : (bool) ($data['active'] ?? $warehouse->isActive()),
            $isDefault
                ? true
                : (bool) ($data['fulfillmentEnabled'] ?? $warehouse->isFulfillmentEnabled()),
            $isDefault ? 0 : max(0, (int) ($data['priority'] ?? $warehouse->getPriority())),
        );
        $entityManager->flush();

        return $this->json(['warehouse' => $this->warehousePayload($warehouse)]);
    }

    #[Route('/stock', methods: ['GET'])]
    public function stock(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $this->inventory->defaultWarehouse($tenant, $entityManager);
        $entityManager->flush();
        $limit = $request->query->getInt('limit');
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim((string) $request->query->get('search', ''));
        $sort = (string) $request->query->get('sort', 'name');
        $direction = strtoupper((string) $request->query->get('direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $locale = $entityManager->getRepository(Locale::class)->findOneBy([
            'code' => $tenant->getDefaultSnippetLocale(),
            'active' => true,
        ]);
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('product')
            ->from(Product::class, 'product')
            ->where('product.tenant = :tenant')
            ->andWhere('product.parent IS NULL')
            ->setParameter('tenant', $tenant);
        if ($search !== '') {
            $queryBuilder
                ->andWhere(
                    'LOWER(COALESCE(product.sku, \'\')) LIKE :search OR EXISTS ('
                    .'SELECT 1 FROM '.ProductTranslation::class.' searchTranslation '
                    .'WHERE searchTranslation.product = product AND LOWER(searchTranslation.name) LIKE :search'
                    .')'
                )
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }
        $sortField = match ($sort) {
            'sku' => 'product.sku',
            'name' => 'sortName',
            'stock' => 'sortStock',
            'availableStock' => 'sortAvailableStock',
            default => 'sortName',
        };
        $sortsByInventoryTotal = in_array($sort, ['stock', 'availableStock'], true);
        if ($sortsByInventoryTotal) {
            $queryBuilder->leftJoin(
                InventoryLevel::class,
                'sortInventoryLevel',
                'WITH',
                'sortInventoryLevel.product = product AND sortInventoryLevel.tenant = :tenant',
            );
            if ($sort === 'stock') {
                $queryBuilder->addSelect(
                    'COALESCE(SUM(sortInventoryLevel.quantity), 0) AS HIDDEN sortStock',
                );
            } else {
                $queryBuilder->addSelect(
                    'COALESCE(SUM(sortInventoryLevel.quantity - sortInventoryLevel.reservedQuantity - sortInventoryLevel.unavailableQuantity), 0) AS HIDDEN sortAvailableStock',
                );
            }
            $queryBuilder->groupBy('product.id');
        } elseif ($locale instanceof Locale) {
            $queryBuilder
                ->addSelect('sortTranslation.name AS HIDDEN sortName')
                ->leftJoin(
                    ProductTranslation::class,
                    'sortTranslation',
                    'WITH',
                    'sortTranslation.product = product AND sortTranslation.locale = :locale',
                )
                ->setParameter('locale', $locale);
        } elseif ($sortField === 'sortName') {
            $sortField = 'product.sku';
        }
        $queryBuilder->orderBy($sortField, $direction);
        $pageSize = $limit > 0 ? min($limit, 100) : null;
        if ($pageSize !== null) {
            $queryBuilder
                ->setMaxResults($pageSize)
                ->setFirstResult(($page - 1) * $pageSize);
        }
        $products = $queryBuilder->getQuery()->getResult();
        $levels = $products === []
            ? []
            : $entityManager->getRepository(InventoryLevel::class)->findBy([
                'tenant' => $tenant,
                'product' => $products,
            ]);
        $totals = [];
        foreach ($levels as $level) {
            $id = $level->getProduct()->getId()->toRfc4122();
            $totals[$id]['stock'] = ($totals[$id]['stock'] ?? 0) + (float) $level->getQuantity();
            $totals[$id]['available'] = ($totals[$id]['available'] ?? 0) + (float) $level->getAvailableQuantity();
            $totals[$id]['unavailable'] = ($totals[$id]['unavailable'] ?? 0) + (float) $level->getUnavailableQuantity();
            $totals[$id]['incoming'] = ($totals[$id]['incoming'] ?? 0) + (float) $level->getIncomingQuantity();
        }
        $names = [];
        if ($locale instanceof Locale && $products !== []) {
            foreach ($entityManager->getRepository(ProductTranslation::class)->findBy(['locale' => $locale, 'product' => $products]) as $translation) {
                $names[$translation->getProduct()->getId()->toRfc4122()] = $translation->getName();
            }
        }
        $response = [
            'items' => array_map(
                fn (Product $product) => [
                    'productId' => $product->getId()->toRfc4122(),
                    'name' => $names[$product->getId()->toRfc4122()] ?? '',
                    'sku' => $product->getSku(),
                    'stock' => $totals[$product->getId()->toRfc4122()]['stock'] ?? 0,
                    'availableStock' => $totals[$product->getId()->toRfc4122()]['available'] ?? 0,
                    'unavailableStock' => $totals[$product->getId()->toRfc4122()]['unavailable'] ?? 0,
                    'incomingStock' => $totals[$product->getId()->toRfc4122()]['incoming'] ?? 0,
                ],
                $products,
            ),
        ];
        if ($pageSize !== null) {
            $totalQuery = $entityManager->createQueryBuilder()
                ->select('COUNT(product.id)')
                ->from(Product::class, 'product')
                ->where('product.tenant = :tenant')
                ->andWhere('product.parent IS NULL')
                ->setParameter('tenant', $tenant);
            if ($search !== '') {
                $totalQuery
                    ->andWhere(
                        'LOWER(COALESCE(product.sku, \'\')) LIKE :search OR EXISTS ('
                        .'SELECT 1 FROM '.ProductTranslation::class.' searchTranslation '
                        .'WHERE searchTranslation.product = product AND LOWER(searchTranslation.name) LIKE :search'
                        .')'
                    )
                    ->setParameter('search', '%'.mb_strtolower($search).'%');
            }
            $total = (int) $totalQuery->getQuery()->getSingleScalarResult();
            $response['pagination'] = [
                'page' => $page,
                'limit' => $pageSize,
                'total' => $total,
                'hasMore' => $page * $pageSize < $total,
            ];
        }

        return $this->json($response);
    }

    #[Route('/products/{id}', methods: ['GET'])]
    public function productStock(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $product = $this->product($id, $tenant, $entityManager);
        $this->inventory->defaultWarehouse($tenant, $entityManager);
        $entityManager->flush();
        $levels = $entityManager->getRepository(InventoryLevel::class)->findBy([
            'tenant' => $tenant,
            'product' => $product,
        ]);
        $levelsByWarehouseId = [];
        foreach ($levels as $level) {
            $levelsByWarehouseId[$level->getWarehouse()->getId()->toRfc4122()] = $level;
        }
        $warehouses = $entityManager->getRepository(Warehouse::class)->findBy(
            ['tenant' => $tenant],
            ['priority' => 'ASC', 'name' => 'ASC'],
        );

        return $this->json([
            'stock' => array_sum(array_map(
                fn (InventoryLevel $level) => (float) $level->getQuantity(),
                $levels,
            )),
            'availableStock' => array_sum(array_map(
                fn (InventoryLevel $level) => (float) $level->getAvailableQuantity(),
                $levels,
            )),
            'unavailableStock' => array_sum(array_map(
                fn (InventoryLevel $level) => (float) $level->getUnavailableQuantity(),
                $levels,
            )),
            'incomingStock' => array_sum(array_map(
                fn (InventoryLevel $level) => (float) $level->getIncomingQuantity(),
                $levels,
            )),
            'levels' => array_map(
                fn (Warehouse $warehouse) => $this->warehouseLevelPayload(
                    $warehouse,
                    $levelsByWarehouseId[$warehouse->getId()->toRfc4122()] ?? null,
                ),
                $warehouses,
            ),
        ]);
    }

    #[Route('/transfers/{id}/send', methods: ['POST'])]
    public function sendTransfer(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $transfer = $this->transfer($id, $tenant, $entityManager);
        if ($transfer->getStatus() !== 'draft') {
            return $this->json(['message' => 'Only draft transfers can be sent.'], Response::HTTP_CONFLICT);
        }

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $entityManager->lock($transfer, LockMode::PESSIMISTIC_WRITE);
            $entityManager->refresh($transfer);
            if ($transfer->getStatus() !== 'draft') {
                throw new \DomainException('Only draft transfers can be sent.');
            }
            $items = $transfer->getItems()->toArray();
            usort(
                $items,
                fn (InventoryTransferItem $a, InventoryTransferItem $b) => strcmp(
                    $a->getProduct()->getId()->toRfc4122(),
                    $b->getProduct()->getId()->toRfc4122(),
                ),
            );
            foreach ($items as $item) {
                $this->inventory->lockProduct($tenant, $transfer->getSourceWarehouse(), $item->getProduct(), $entityManager);
                $this->inventory->transferOut(
                    $tenant,
                    $transfer->getSourceWarehouse(),
                    $item->getProduct(),
                    $item->getQuantity(),
                    $this->getUser() instanceof User ? $this->getUser() : null,
                    $transfer->getId()->toRfc4122(),
                    $transfer->getNote(),
                    $entityManager,
                );
            }
            $transfer->markSent();
            $entityManager->flush();
            $connection->commit();
        } catch (\DomainException $exception) {
            $connection->rollBack();

            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['transfer' => $this->transferPayload($transfer)]);
    }

    #[Route('/counts', methods: ['POST'])]
    public function createCount(
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $warehouse = $this->warehouse((string) ($data['warehouseId'] ?? ''), $tenant, $entityManager);
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        if (!$warehouse->isActive() || $items === []) {
            return $this->json(['message' => 'Choose an active warehouse and at least one counted product.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $count = new InventoryCount($tenant, $warehouse, $this->nullable($data['note'] ?? null));
        $seenProducts = [];
        foreach ($items as $item) {
            if (!is_array($item) || !is_numeric($item['countedQuantity'] ?? null) || (float) $item['countedQuantity'] < 0) {
                return $this->json(['message' => 'Each count line needs a non-negative counted quantity.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $product = $this->product((string) ($item['productId'] ?? ''), $tenant, $entityManager);
            $productId = $product->getId()->toRfc4122();
            if (isset($seenProducts[$productId])) {
                return $this->json(['message' => 'A product may appear only once in a count.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $seenProducts[$productId] = true;
            $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
                'warehouse' => $warehouse,
                'product' => $product,
            ]);
            $count->addItem(
                $product,
                $level instanceof InventoryLevel ? $level->getQuantity() : '0.0000',
                $level instanceof InventoryLevel ? $level->getVersion() : null,
                number_format((float) $item['countedQuantity'], 4, '.', ''),
            );
        }
        $entityManager->persist($count);
        $entityManager->flush();

        return $this->json(['count' => $this->countPayload($count)], Response::HTTP_CREATED);
    }

    #[Route('/counts', methods: ['GET'])]
    public function counts(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 25)));
        $total = $entityManager->getRepository(InventoryCount::class)->count(['tenant' => $tenant]);
        $counts = $entityManager->getRepository(InventoryCount::class)->findBy(
            ['tenant' => $tenant],
            ['createdAt' => 'DESC'],
            $limit,
            ($page - 1) * $limit,
        );
        if ($counts !== []) {
            $entityManager->createQueryBuilder()
                ->select('inventoryCount', 'items', 'product', 'warehouse')
                ->from(InventoryCount::class, 'inventoryCount')
                ->leftJoin('inventoryCount.items', 'items')
                ->leftJoin('items.product', 'product')
                ->join('inventoryCount.warehouse', 'warehouse')
                ->where('inventoryCount IN (:counts)')
                ->setParameter('counts', $counts)
                ->getQuery()
                ->getResult();
        }

        return $this->json([
            'counts' => array_map($this->countPayload(...), $counts),
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'hasMore' => $page * $limit < $total],
        ]);
    }

    #[Route('/counts/{id}/post', methods: ['POST'])]
    public function postCount(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $count = $this->count($id, $tenant, $entityManager);
        if ($count->getStatus() !== 'draft') {
            return $this->json(['message' => 'Only draft counts can be posted.'], Response::HTTP_CONFLICT);
        }
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $entityManager->lock($count, LockMode::PESSIMISTIC_WRITE);
            $entityManager->refresh($count);
            if ($count->getStatus() !== 'draft') {
                throw new \DomainException('Only draft counts can be posted.');
            }
            $items = $count->getItems()->toArray();
            usort(
                $items,
                fn (InventoryCountItem $a, InventoryCountItem $b) => strcmp(
                    $a->getProduct()->getId()->toRfc4122(),
                    $b->getProduct()->getId()->toRfc4122(),
                ),
            );
            foreach ($items as $item) {
                $this->inventory->lockProduct($tenant, $count->getWarehouse(), $item->getProduct(), $entityManager);
                $level = $entityManager->getRepository(InventoryLevel::class)->findOneBy([
                    'tenant' => $tenant,
                    'warehouse' => $count->getWarehouse(),
                    'product' => $item->getProduct(),
                ]);
                if ($level instanceof InventoryLevel) {
                    $entityManager->refresh($level);
                }
                $current = $level instanceof InventoryLevel ? $level->getQuantity() : '0.0000';
                $version = $level instanceof InventoryLevel ? $level->getVersion() : null;
                if ($version !== $item->getExpectedVersion() || number_format((float) $current, 4, '.', '') !== $item->getExpectedQuantity()) {
                    throw new \DomainException('Stock changed after this count was created. Create a fresh count before posting.');
                }
                if ($level instanceof InventoryLevel && (float) $item->getCountedQuantity() < (float) $level->getReservedQuantity() + (float) $level->getUnavailableQuantity()) {
                    throw new \DomainException('Counted stock cannot be lower than reserved and unavailable stock.');
                }
                $this->inventory->setStock(
                    $tenant,
                    $count->getWarehouse(),
                    $item->getProduct(),
                    $item->getCountedQuantity(),
                    $this->getUser() instanceof User ? $this->getUser() : null,
                    sprintf('Count %s%s', $count->getId()->toRfc4122(), $count->getNote() ? ': '.$count->getNote() : ''),
                    $entityManager,
                );
            }
            $count->post();
            $entityManager->flush();
            $connection->commit();
        } catch (\DomainException $exception) {
            $connection->rollBack();

            return $this->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['count' => $this->countPayload($count)]);
    }

    #[Route('/transfers', methods: ['POST'])]
    public function createTransfer(
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $sourceId = (string) ($data['sourceWarehouseId'] ?? '');
        $destinationId = (string) ($data['destinationWarehouseId'] ?? '');
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        if ($sourceId === '' || $destinationId === '' || $sourceId === $destinationId || $items === []) {
            return $this->json(['message' => 'Choose different warehouses and at least one product.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $source = $this->warehouse($sourceId, $tenant, $entityManager);
        $destination = $this->warehouse($destinationId, $tenant, $entityManager);
        if (!$source->isActive() || !$destination->isActive()) {
            return $this->json(['message' => 'Both warehouses must be active.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $transfer = new InventoryTransfer($tenant, $source, $destination, $this->nullable($data['note'] ?? null));
        $seenProducts = [];
        foreach ($items as $item) {
            if (!is_array($item) || !is_numeric($item['quantity'] ?? null) || (float) $item['quantity'] <= 0) {
                return $this->json(['message' => 'Each transfer line needs a positive quantity.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $product = $this->product((string) ($item['productId'] ?? ''), $tenant, $entityManager);
            $productId = $product->getId()->toRfc4122();
            if (isset($seenProducts[$productId])) {
                return $this->json(['message' => 'A product may appear only once in a transfer.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $seenProducts[$productId] = true;
            $transfer->addItem(
                $product,
                number_format((float) $item['quantity'], 4, '.', ''),
            );
        }
        $entityManager->persist($transfer);
        $entityManager->flush();

        return $this->json(['transfer' => $this->transferPayload($transfer)], Response::HTTP_CREATED);
    }

    #[Route('/transfers', methods: ['GET'])]
    public function transfers(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 25)));
        $total = $entityManager->getRepository(InventoryTransfer::class)->count(['tenant' => $tenant]);
        $transfers = $entityManager->getRepository(InventoryTransfer::class)->findBy(
            ['tenant' => $tenant],
            ['createdAt' => 'DESC'],
            $limit,
            ($page - 1) * $limit,
        );
        if ($transfers !== []) {
            $entityManager->createQueryBuilder()
                ->select('transfer', 'items', 'product', 'source', 'destination')
                ->from(InventoryTransfer::class, 'transfer')
                ->leftJoin('transfer.items', 'items')
                ->leftJoin('items.product', 'product')
                ->join('transfer.sourceWarehouse', 'source')
                ->join('transfer.destinationWarehouse', 'destination')
                ->where('transfer IN (:transfers)')
                ->setParameter('transfers', $transfers)
                ->getQuery()
                ->getResult();
        }

        return $this->json([
            'transfers' => array_map($this->transferPayload(...), $transfers),
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total, 'hasMore' => $page * $limit < $total],
        ]);
    }

    #[Route('/transfers/{id}/receive', methods: ['POST'])]
    public function receiveTransfer(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $transfer = $this->transfer($id, $tenant, $entityManager);
        if ($transfer->getStatus() !== 'in_transit') {
            return $this->json(['message' => 'Only in-transit transfers can be received.'], Response::HTTP_CONFLICT);
        }

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $entityManager->lock($transfer, LockMode::PESSIMISTIC_WRITE);
            $entityManager->refresh($transfer);
            if ($transfer->getStatus() !== 'in_transit') {
                throw new \DomainException('Only in-transit transfers can be received.');
            }
            $items = $transfer->getItems()->toArray();
            usort(
                $items,
                fn (InventoryTransferItem $a, InventoryTransferItem $b) => strcmp(
                    $a->getProduct()->getId()->toRfc4122(),
                    $b->getProduct()->getId()->toRfc4122(),
                ),
            );
            foreach ($items as $item) {
                $this->inventory->lockProduct($tenant, $transfer->getDestinationWarehouse(), $item->getProduct(), $entityManager);
                $this->inventory->transferIn(
                    $tenant,
                    $transfer->getDestinationWarehouse(),
                    $item->getProduct(),
                    $item->getQuantity(),
                    $this->getUser() instanceof User ? $this->getUser() : null,
                    $transfer->getId()->toRfc4122(),
                    $transfer->getNote(),
                    $entityManager,
                );
            }
            $transfer->markReceived();
            $entityManager->flush();
            $connection->commit();
        } catch (\DomainException $exception) {
            $connection->rollBack();

            return $this->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['transfer' => $this->transferPayload($transfer)]);
    }

    #[Route('/transfers/{id}/cancel', methods: ['POST'])]
    public function cancelTransfer(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $transfer = $this->transfer($id, $tenant, $entityManager);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $entityManager->lock($transfer, LockMode::PESSIMISTIC_WRITE);
            $entityManager->refresh($transfer);
            if ($transfer->getStatus() !== 'draft') {
                throw new \DomainException('Only draft transfers can be cancelled. Receive in-transit goods first.');
            }
            $transfer->cancel();
            $entityManager->flush();
            $connection->commit();
        } catch (\DomainException $exception) {
            $connection->rollBack();

            return $this->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->json(['transfer' => $this->transferPayload($transfer)]);
    }

    #[Route('/products/{id}', methods: ['PUT'])]
    public function setProductStock(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $product = $this->product($id, $tenant, $entityManager);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $warehouse = isset($data['warehouseId']) ? $this->warehouse((string) $data['warehouseId'], $tenant, $entityManager) : $this->inventory->defaultWarehouse($tenant, $entityManager);
        if (!$warehouse->isActive() || !is_numeric($data['quantity'] ?? null)) {
            return $this->validation(
                $translator,
                array_filter([
                    !$warehouse->isActive() ? 'warehouse' : null,
                    !is_numeric($data['quantity'] ?? null) ? 'quantity' : null,
                ]),
            );
        }
        if ((float) $data['quantity'] < 0) {
            return $this->json(['message' => 'Stock cannot be negative.'], Response::HTTP_UNPROCESSABLE_ENTITY);
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
            if ($level instanceof InventoryLevel) {
                $entityManager->refresh($level);
            }
            if ($level instanceof InventoryLevel && (float) $data['quantity'] < (float) $level->getReservedQuantity() + (float) $level->getUnavailableQuantity()) {
                throw new \DomainException('Stock cannot be lower than reserved and unavailable stock.');
            }
            $this->inventory->setStock($tenant, $warehouse, $product, (string) $data['quantity'], $this->getUser() instanceof User ? $this->getUser() : null, $this->nullable($data['note'] ?? null), $entityManager);
            $entityManager->flush();
            $connection->commit();
        } catch (\DomainException $exception) {
            $connection->rollBack();

            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $this->productStock($id, $entityManager);
    }

    #[Route('/products/{id}/movements', methods: ['GET'])]
    public function movements(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $product = $this->product($id, $tenant, $entityManager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(max(1, $request->query->getInt('limit', 25)), 100);
        $total = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(movement.id)')
            ->from(InventoryMovement::class, 'movement')
            ->where('movement.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();
        $items = $entityManager->createQueryBuilder()
            ->select('movement', 'warehouse')
            ->from(InventoryMovement::class, 'movement')
            ->leftJoin('movement.warehouse', 'warehouse')
            ->where('movement.product = :product')
            ->setParameter('product', $product)
            ->orderBy('movement.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'movements' => array_map(
                fn (InventoryMovement $item) => [
                    'id' => $item->getId()->toRfc4122(),
                    'warehouse' => $item->getWarehouse()->getName(),
                    'type' => $item->getType(),
                    'quantityDelta' => (float) $item->getQuantityDelta(),
                    'quantityAfter' => (float) $item->getQuantityAfter(),
                    'note' => $item->getNote(),
                    'createdAt' => $item->getCreatedAt()->format(DATE_ATOM),
                ],
                $items,
            ),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'hasMore' => $page * $limit < $total,
            ],
        ]);
    }

    private function tenant(EntityManagerInterface $entityManager, bool $owner = false): Tenant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        } $membership = $entityManager->getRepository(TenantMembership::class)->findOneBy(['user' => $user]);
        if (!$membership instanceof TenantMembership || ($owner && $membership->getRole() !== 'owner')) {
            throw $this->createAccessDeniedException();
        }

return $membership->getTenant();
    }
    private function product(string $id, Tenant $tenant, EntityManagerInterface $entityManager): Product
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        } $product = $entityManager->getRepository(Product::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant]);
        if (!$product instanceof Product) {
            throw $this->createNotFoundException();
        }

return $product;
    }
    private function warehouse(string $id, Tenant $tenant, EntityManagerInterface $entityManager): Warehouse
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        } $warehouse = $entityManager->getRepository(Warehouse::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant]);
        if (!$warehouse instanceof Warehouse) {
            throw $this->createNotFoundException();
        }

return $warehouse;
    }

    private function transfer(string $id, Tenant $tenant, EntityManagerInterface $entityManager): InventoryTransfer
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $transfer = $entityManager->getRepository(InventoryTransfer::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);
        if (!$transfer instanceof InventoryTransfer) {
            throw $this->createNotFoundException();
        }

        return $transfer;
    }

    private function count(string $id, Tenant $tenant, EntityManagerInterface $entityManager): InventoryCount
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $count = $entityManager->getRepository(InventoryCount::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);
        if (!$count instanceof InventoryCount) {
            throw $this->createNotFoundException();
        }

        return $count;
    }

    private function countPayload(InventoryCount $count): array
    {
        return [
            'id' => $count->getId()->toRfc4122(),
            'warehouse' => $count->getWarehouse()->getName(),
            'status' => $count->getStatus(),
            'note' => $count->getNote(),
            'createdAt' => $count->getCreatedAt()->format(DATE_ATOM),
            'items' => array_map(
                fn (InventoryCountItem $item) => [
                    'productId' => $item->getProduct()->getId()->toRfc4122(),
                    'sku' => $item->getProduct()->getSku(),
                    'expectedQuantity' => (float) $item->getExpectedQuantity(),
                    'countedQuantity' => (float) $item->getCountedQuantity(),
                    'variance' => (float) $item->getCountedQuantity() - (float) $item->getExpectedQuantity(),
                ],
                $count->getItems()->toArray(),
            ),
        ];
    }

    private function transferPayload(InventoryTransfer $transfer): array
    {
        return [
            'id' => $transfer->getId()->toRfc4122(),
            'status' => $transfer->getStatus(),
            'sourceWarehouse' => $transfer->getSourceWarehouse()->getName(),
            'destinationWarehouse' => $transfer->getDestinationWarehouse()->getName(),
            'note' => $transfer->getNote(),
            'createdAt' => $transfer->getCreatedAt()->format(DATE_ATOM),
            'sentAt' => $transfer->getSentAt()?->format(DATE_ATOM),
            'receivedAt' => $transfer->getReceivedAt()?->format(DATE_ATOM),
            'items' => array_map(
                fn (InventoryTransferItem $item) => [
                    'productId' => $item->getProduct()->getId()->toRfc4122(),
                    'sku' => $item->getProduct()->getSku(),
                    'quantity' => (float) $item->getQuantity(),
                ],
                $transfer->getItems()->toArray(),
            ),
        ];
    }
    private function warehousePayload(Warehouse $warehouse): array
    {
        return [
            'id' => $warehouse->getId()->toRfc4122(),
            'code' => $warehouse->getCode(),
            'name' => $warehouse->getName(),
            'active' => $warehouse->isActive(),
            'fulfillmentEnabled' => $warehouse->isFulfillmentEnabled(),
            'priority' => $warehouse->getPriority(),
            'default' => $warehouse->getCode() === 'default',
        ];
    }
    private function warehouseLevelPayload(Warehouse $warehouse, ?InventoryLevel $level): array
    {
        return [
            'warehouseId' => $warehouse->getId()->toRfc4122(),
            'warehouse' => $warehouse->getName(),
            'active' => $warehouse->isActive(),
            'fulfillmentEnabled' => $warehouse->isFulfillmentEnabled(),
            'priority' => $warehouse->getPriority(),
            'stock' => $level instanceof InventoryLevel ? (float) $level->getQuantity() : 0,
            'reservedStock' => $level instanceof InventoryLevel ? (float) $level->getReservedQuantity() : 0,
            'unavailableStock' => $level instanceof InventoryLevel ? (float) $level->getUnavailableQuantity() : 0,
            'incomingStock' => $level instanceof InventoryLevel ? (float) $level->getIncomingQuantity() : 0,
            'availableStock' => $level instanceof InventoryLevel ? (float) $level->getAvailableQuantity() : 0,
        ];
    }
    private function data(Request $request, TranslatorInterface $translator): array|JsonResponse
    {
        try {
            return $request->toArray();
        } catch (\JsonException) {
            return $this->invalid($translator);
        }
    }
    private function invalid(TranslatorInterface $translator): JsonResponse
    {
        return $this->json(['message' => $translator->trans('product.invalid', locale: $this->getUser() instanceof User ? $this->getUser()->getLocale() : 'bs')], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /** @param list<string> $fields */
    private function validation(
        TranslatorInterface $translator,
        array $fields,
        bool $required = false,
    ): JsonResponse {
        $locale = $this->getUser() instanceof User ? $this->getUser()->getLocale() : 'bs';
        $labels = array_map(
            fn (string $field): string => $translator->trans('field.'.$field, locale: $locale),
            $fields,
        );

        return $this->json([
            'message' => $translator->trans(
                $required ? 'validation.required_fields' : 'validation.invalid_fields',
                ['%fields%' => implode(', ', $labels)],
                locale: $locale,
            ),
            'errors' => array_fill_keys(
                $fields,
                $translator->trans(
                    $required ? 'validation.required_field' : 'validation.invalid_field',
                    locale: $locale,
                ),
            ),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
    private function code(string $value): string
    {
        $value = strtolower(trim($value));

        return trim(preg_replace('/[^a-z0-9]+/u', '_', $value) ?? '', '_');
    }
}
