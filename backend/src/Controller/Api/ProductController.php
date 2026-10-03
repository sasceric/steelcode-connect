<?php

namespace App\Controller\Api;

use App\Entity\Currency;
use App\Entity\Category;
use App\Entity\CategoryProduct;
use App\Entity\Locale;
use App\Entity\Manufacturer;
use App\Entity\ManufacturerTranslation;
use App\Entity\Media;
use App\Entity\InventoryLevel;
use App\Entity\Product;
use App\Entity\ProductCrossSelling;
use App\Entity\ProductCrossSellingAssignment;
use App\Entity\ProductCrossSellingTranslation;
use App\Entity\ProductDownload;
use App\Entity\ProductExtensionValue;
use App\Entity\ProductMedia;
use App\Entity\ProductOptionGroup;
use App\Entity\ProductOptionValue;
use App\Entity\ProductPrice;
use App\Entity\ProductPropertyAssignment;
use App\Entity\ProductTag;
use App\Entity\ProductTranslation;
use App\Entity\ProductVariantOptionGroup;
use App\Entity\ProductVariantOptionGroupProperty;
use App\Entity\ProductVariantOptionValue;
use App\Entity\Property;
use App\Entity\PropertyGroup;
use App\Entity\Tag;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Http\PrivateMediaResponse;
use App\Entity\User;
use App\Service\SeoUrlService;
use App\Service\PropertyValueReader;
use App\Service\ProductVariantConfigurationReader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/products')]
final class ProductController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SeoUrlService $seoUrls,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $optionsOnly = $request->query->get('view') === 'options';
        $includeVariants = $optionsOnly && $request->query->getBoolean('includeVariants');
        $requestedIds = array_filter(
            explode(',', (string) $request->query->get('ids', '')),
            static fn (string $id): bool => Uuid::isValid($id),
        );
        if ($requestedIds !== []) {
            $products = [];
            foreach ($requestedIds as $requestedId) {
                $product = $entityManager->getRepository(Product::class)->findOneBy([
                    'id' => Uuid::fromString($requestedId),
                    'tenant' => $tenant,
                ]);
                if ($product instanceof Product) {
                    $products[] = $product;
                }
            }
            if ($optionsOnly) {
                return $this->json([
                    'products' => $this->optionPayloads($products, $tenant),
                ]);
            }
            $covers = $this->coverMediaByProductId($products);
            $productIdsWithVariants = $this->productIdsWithVariants($products);
            $stockByProductId = $this->stockByProductId($products);

            return $this->json([
                'products' => array_map(
                    fn (Product $product): array => $this->listPayload(
                        $product,
                        $covers[$product->getId()->toRfc4122()] ?? null,
                        isset($productIdsWithVariants[$product->getId()->toRfc4122()]),
                        $stockByProductId[$product->getId()->toRfc4122()] ?? 0,
                    ),
                    $products,
                ),
            ]);
        }
        $limit = $request->query->getInt('limit');
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim((string) $request->query->get('search', ''));
        $includeSearchVariants = !$optionsOnly && $search !== '';
        $status = (string) $request->query->get('status', '');
        $categoryId = (string) $request->query->get('categoryId', '');
        $manufacturerId = (string) $request->query->get('manufacturerId', '');
        $sort = (string) $request->query->get('sort', 'updatedAt');
        $direction = strtoupper((string) $request->query->get('direction', 'DESC')) === 'ASC'
            ? 'ASC'
            : 'DESC';
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('product')
            ->from(Product::class, 'product');
        $queryBuilder
            ->where('product.tenant = :tenant')
            ->setParameter('tenant', $tenant);

        if (!$includeVariants && !$includeSearchVariants) {
            $queryBuilder->andWhere('product.parent IS NULL');
        }

        if (Uuid::isValid($categoryId)) {
            $queryBuilder
                ->andWhere(
                    'EXISTS (SELECT 1 FROM '.CategoryProduct::class.' categoryProduct '
                    .'WHERE categoryProduct.product = product AND IDENTITY(categoryProduct.category) = :categoryId)'
                )
                ->setParameter('categoryId', Uuid::fromString($categoryId));
        }

        if (Uuid::isValid($manufacturerId)) {
            $queryBuilder
                ->andWhere('IDENTITY(product.manufacturer) = :manufacturerId')
                ->setParameter('manufacturerId', Uuid::fromString($manufacturerId));
        }

        if (in_array($status, ['draft', 'active', 'archived'], true)) {
            $queryBuilder
                ->andWhere('product.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== '') {
            $queryBuilder
                ->andWhere(
                    'LOWER(product.sku) LIKE :search OR EXISTS ('
                    .'SELECT 1 FROM '.ProductTranslation::class.' searchTranslation '
                    .'WHERE searchTranslation.product = product AND LOWER(searchTranslation.name) LIKE :search'
                    .') OR EXISTS ('
                    .'SELECT 1 FROM '.ProductTranslation::class.' parentSearchTranslation '
                    .'WHERE parentSearchTranslation.product = product.parent AND LOWER(parentSearchTranslation.name) LIKE :search'
                    .')'
                )
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        $sortField = match ($sort) {
            'name' => 'sortName',
            'sku' => 'product.sku',
            'status' => 'product.status',
            'updatedAt' => 'product.updatedAt',
            default => 'product.updatedAt',
        };
        if ($sort === 'name') {
            $sortLocale = $entityManager->getRepository(Locale::class)->findOneBy([
                'code' => $tenant->getDefaultSnippetLocale(),
            ]);
            if ($sortLocale instanceof Locale) {
                $queryBuilder
                    ->addSelect('sortTranslation.name AS HIDDEN sortName')
                    ->leftJoin(
                        ProductTranslation::class,
                        'sortTranslation',
                        'WITH',
                        'sortTranslation.product = product AND sortTranslation.locale = :sortLocale'
                    )
                    ->setParameter('sortLocale', $sortLocale);
            } else {
                $sortField = 'product.updatedAt';
            }
        }
        $queryBuilder->orderBy($sortField, $direction);

        $pageSize = $limit > 0 ? min($limit, 100) : null;
        if ($pageSize !== null) {
            $queryBuilder
                ->setMaxResults($pageSize)
                ->setFirstResult(($page - 1) * $pageSize);
        }

        if ($optionsOnly) {
            $queryBuilder
                ->addSelect('optionUnit')
                ->leftJoin('product.unit', 'optionUnit');
        }
        $products = $queryBuilder->getQuery()->getResult();
        if ($optionsOnly) {
            $response = [
                'products' => $this->optionPayloads($products, $tenant),
            ];

            if ($limit > 0) {
                $total = $this->productListTotal($tenant, $search, $status, $categoryId, $manufacturerId, $includeVariants);
                $response['pagination'] = [
                    'page' => $page,
                    'limit' => $pageSize,
                    'total' => $total,
                    'hasMore' => $page * $pageSize < $total,
                ];
            }

            return $this->json($response);
        }
        $covers = $this->coverMediaByProductId($products);
        $productIdsWithVariants = $this->productIdsWithVariants($products);
        $stockByProductId = $this->stockByProductId($products);

        $response = [
            'products' => array_map(
                fn (Product $product): array => $this->listPayload(
                    $product,
                    $covers[$product->getId()->toRfc4122()] ?? null,
                    isset($productIdsWithVariants[$product->getId()->toRfc4122()]),
                    $stockByProductId[$product->getId()->toRfc4122()] ?? 0,
                ),
                $products,
            ),
        ];

        if ($limit > 0) {
            $total = $this->productListTotal(
                $tenant,
                $search,
                $status,
                $categoryId,
                $manufacturerId,
                $includeSearchVariants,
            );
            $response['pagination'] = [
                'page' => $page,
                'limit' => $pageSize,
                'total' => $total,
                'hasMore' => $page * $pageSize < $total,
            ];
        }

        return $this->json($response);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $this->json(['message' => $this->message($translator, 'product.name_required')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entityManager->beginTransaction();
        try {
            $lockedTenant = $entityManager->find(
                Tenant::class,
                $tenant->getId(),
                LockMode::PESSIMISTIC_WRITE,
            );
            if (!$lockedTenant instanceof Tenant) {
                throw $this->createNotFoundException();
            }

            $product = new Product($lockedTenant);
            $product->updateSku((string) $lockedTenant->claimNextProductNumber());
            $translation = $this->newDefaultTranslation($product, $name);
            $entityManager->persist($product);
            $entityManager->persist($translation);
            $entityManager->flush();
            $entityManager->commit();
        } catch (\Throwable $exception) {
            $entityManager->rollback();

            throw $exception;
        }

        return $this->json(['product' => $this->payload($product)], Response::HTTP_CREATED);
    }

    #[Route('/next-number', methods: ['GET'], priority: 10)]
    public function nextProductNumber(EntityManagerInterface $entityManager): JsonResponse
    {
        return $this->json([
            'productNumber' => (string) $this->tenant($entityManager)->getNextProductNumber(),
        ]);
    }

    #[Route('', methods: ['DELETE'])]
    public function deleteMany(Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response|JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $ids = $data['ids'] ?? [];
        if (!is_array($ids) || $ids === [] || count($ids) > 100) {
            return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $products = [];
        $seenIds = [];
        foreach ($ids as $id) {
            if (!is_string($id) || !Uuid::isValid($id)) {
                return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            if (isset($seenIds[$id])) {
                continue;
            }
            $seenIds[$id] = true;

            $product = $entityManager->getRepository(Product::class)->findOneBy([
                'id' => Uuid::fromString($id),
                'tenant' => $tenant,
                'parent' => null,
            ]);
            if (!$product instanceof Product) {
                return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $products[] = $product;
        }

        foreach ($products as $product) {
            $variants = $entityManager->getRepository(Product::class)->findBy([
                'parent' => $product,
            ]);
            $this->seoUrls->removeForEntity(
                $tenant,
                SeoUrlService::ENTITY_PRODUCT,
                $product->getId(),
            );
            foreach ($variants as $variant) {
                if ($variant instanceof Product) {
                    $this->seoUrls->removeForEntity(
                        $tenant,
                        SeoUrlService::ENTITY_PRODUCT,
                        $variant->getId(),
                    );
                }
            }
            $entityManager->remove($product);
        }
        $entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        return $this->json(['product' => $this->payload($this->product($id, $entityManager))]);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $product = $this->product($id, $entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $status = (string) ($data['status'] ?? $product->getStatus());
        if (!in_array($status, ['draft', 'active', 'archived'], true)) {
            return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $productType = (string) ($data['productType'] ?? $product->getProductType());
        if (!in_array($productType, ['physical', 'digital', 'service'], true)) {
            return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $releaseDate = array_key_exists('releaseDate', $data) ? $this->date($data['releaseDate']) : $product->getReleaseDate();
        if (array_key_exists('releaseDate', $data) && $data['releaseDate'] !== null && $releaseDate === null) {
            return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $product->updateStatus($status);
        $sku = array_key_exists('sku', $data) ? $this->nullable($data['sku']) : $product->getSku();
        $existingSku = $sku === null ? null : $entityManager->getRepository(Product::class)->findOneBy(['tenant' => $product->getTenant(), 'sku' => $sku]);
        if ($existingSku instanceof Product && $existingSku->getId()->toRfc4122() !== $product->getId()->toRfc4122()) {
            return $this->json(['message' => $this->message($translator, 'product.sku_used')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $product->updateSku($sku);
        $product->updateCommerce($productType, $this->nullable($data['manufacturerNumber'] ?? $product->getManufacturerNumber()), $this->nullable($data['shippingClass'] ?? $product->getShippingClass()), $this->nullable($data['deliveryTime'] ?? $product->getDeliveryTime()), $releaseDate, (bool) ($data['isFeatured'] ?? $product->isFeatured()), is_array($data['visibility'] ?? null) ? $data['visibility'] : $product->getVisibility());
        $product->updatePackUnits(
            $this->nullable($data['packUnit'] ?? $product->getPackUnit()),
            $this->nullable($data['packUnitPlural'] ?? $product->getPackUnitPlural()),
        );
        $minPurchaseQuantity = $this->decimal($data['minPurchaseQuantity'] ?? $product->getMinPurchaseQuantity()) ?? '1.0000';
        $purchaseSteps = $this->decimal($data['purchaseSteps'] ?? $product->getPurchaseSteps()) ?? '1.0000';
        $maxPurchaseQuantity = array_key_exists('maxPurchaseQuantity', $data) ? $this->decimal($data['maxPurchaseQuantity']) : $product->getMaxPurchaseQuantity();
        if ((float) $minPurchaseQuantity <= 0 || (float) $purchaseSteps <= 0) {
            return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $product->updateFulfilment($minPurchaseQuantity, $purchaseSteps, $maxPurchaseQuantity, $this->integer($data['restockTimeDays'] ?? $product->getRestockTimeDays()), (bool) ($data['clearanceSale'] ?? $product->isClearanceSale()), (bool) ($data['freeShipping'] ?? $product->isFreeShipping()), $this->nullable($data['searchKeywords'] ?? $product->getSearchKeywords()), $this->integer($data['weightGrams'] ?? $product->getWeightGrams()), $this->integer($data['lengthMillimeters'] ?? $product->getLengthMillimeters()), $this->integer($data['widthMillimeters'] ?? $product->getWidthMillimeters()), $this->integer($data['heightMillimeters'] ?? $product->getHeightMillimeters()));
        if (array_key_exists('regularPrice', $data)) {
            $price = is_array($data['regularPrice']) ? $data['regularPrice'] : [];
            $currencyCode = strtoupper((string) ($price['currency'] ?? ''));
            $taxRateValue = trim((string) ($price['taxRate'] ?? ''));
            $grossAmountValue = trim((string) ($price['grossAmount'] ?? ''));
            $netAmountValue = trim((string) ($price['netAmount'] ?? ''));
            $missingFields = [];
            if ($currencyCode === '') {
                $missingFields['regularPrice.currency'] = 'product.field.currency';
            }
            if ($taxRateValue === '') {
                $missingFields['taxId'] = 'product.field.tax_rate';
            }
            if ($grossAmountValue === '') {
                $missingFields['regularPrice.sellingGross'] = 'product.field.gross_price';
            }
            if ($netAmountValue === '') {
                $missingFields['regularPrice.sellingNet'] = 'product.field.net_price';
            }
            if ($missingFields !== []) {
                return $this->validation($translator, $missingFields, true);
            }

            $taxRate = $this->decimal($price['taxRate'] ?? null);
            $grossAmount = $this->money($price['grossAmount'] ?? null);
            $netAmount = $this->money($price['netAmount'] ?? null);
            $currency = $this->currency($currencyCode, $entityManager);
            $invalidFields = [];
            if (!$currency instanceof Currency) {
                $invalidFields['regularPrice.currency'] = 'product.field.currency';
            }
            if ($taxRate === null) {
                $invalidFields['taxId'] = 'product.field.tax_rate';
            }
            if ($grossAmount === null) {
                $invalidFields['regularPrice.sellingGross'] = 'product.field.gross_price';
            }
            if ($netAmount === null) {
                $invalidFields['regularPrice.sellingNet'] = 'product.field.net_price';
            }
            if ($invalidFields !== []) {
                return $this->validation($translator, $invalidFields);
            }
            $linked = (bool) ($price['linked'] ?? true);
            $entry = $this->priceEntry($currency, $grossAmount, $netAmount, $linked, $this->money($price['listGrossAmount'] ?? null), $this->money($price['listNetAmount'] ?? null), (bool) ($price['listLinked'] ?? $linked));
            $purchase = $this->priceEntry($currency, $this->money($price['purchaseGrossAmount'] ?? null), $this->money($price['purchaseNetAmount'] ?? null), (bool) ($price['purchaseLinked'] ?? true));
            $product->updatePrices([$currency->getId()->toRfc4122() => $entry], $purchase === null ? [] : [$currency->getId()->toRfc4122() => $purchase], $product->getCheapestPrice());
        }
        $entityManager->flush();

        return $this->json(['product' => $this->payload($product)]);
    }

    #[Route('/{id}/variants', methods: ['GET'])]
    public function variants(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse
    {
        $product = $this->product($id, $entityManager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(max(1, $request->query->getInt('limit', 25)), 100);
        $search = trim((string) $request->query->get('search', ''));
        $sort = (string) $request->query->get('sort', 'createdAt');
        $direction = strtoupper((string) $request->query->get('direction', 'ASC')) === 'DESC'
            ? 'DESC'
            : 'ASC';

        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('variant')
            ->from(Product::class, 'variant')
            ->where('variant.parent = :product')
            ->andWhere('variant.tenant = :tenant')
            ->setParameter('tenant', $product->getTenant())
            ->setParameter('product', $product);

        if ($search !== '') {
            $queryBuilder
                ->andWhere(
                    'LOWER(COALESCE(variant.sku, \'\')) LIKE :search OR EXISTS ('
                    .'SELECT 1 FROM '.ProductTranslation::class.' searchTranslation '
                    .'WHERE searchTranslation.product = variant AND LOWER(searchTranslation.name) LIKE :search'
                    .')'
                )
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        $sortField = match ($sort) {
            'name' => 'sortName',
            'sku' => 'variant.sku',
            'status' => 'variant.status',
            default => 'variant.createdAt',
        };
        if ($sort === 'name') {
            $locale = $this->defaultLocale($product->getTenant(), $entityManager);
            $queryBuilder
                ->addSelect('sortTranslation.name AS HIDDEN sortName')
                ->leftJoin(
                    ProductTranslation::class,
                    'sortTranslation',
                    'WITH',
                    'sortTranslation.product = variant AND sortTranslation.locale = :sortLocale',
                )
                ->setParameter('sortLocale', $locale);
        }

        $variants = $queryBuilder
            ->orderBy($sortField, $direction)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        $totalQuery = $entityManager->createQueryBuilder()
            ->select('COUNT(variant.id)')
            ->from(Product::class, 'variant')
            ->where('variant.parent = :product')
            ->andWhere('variant.tenant = :tenant')
            ->setParameter('tenant', $product->getTenant())
            ->setParameter('product', $product);

        if ($search !== '') {
            $totalQuery
                ->andWhere(
                    'LOWER(COALESCE(variant.sku, \'\')) LIKE :search OR EXISTS ('
                    .'SELECT 1 FROM '.ProductTranslation::class.' searchTranslation '
                    .'WHERE searchTranslation.product = variant AND LOWER(searchTranslation.name) LIKE :search'
                    .')'
                )
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }
        $total = (int) $totalQuery->getQuery()->getSingleScalarResult();

        return $this->json([
            'variants' => $this->variantPayloads($variants, $product),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'hasMore' => $page * $limit < $total,
            ],
        ]);
    }

    #[Route('/{id}/tags', methods: ['GET'])]
    public function tags(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $product = $this->product($id, $entityManager);
        $assignments = $entityManager->getRepository(ProductTag::class)->findBy(['product' => $product]);

        return $this->json(['tags' => array_map(static fn (ProductTag $assignment) => ['id' => $assignment->getTag()->getId()->toRfc4122(), 'name' => $assignment->getTag()->getName()], $assignments)]);
    }

    #[Route('/{id}/tags', methods: ['PUT'])]
    public function updateTags(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $product = $this->product($id, $entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $names = $data['names'] ?? [];
        if (!is_array($names)) {
            return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $names = array_values(array_unique(array_filter(array_map(fn (mixed $name) => $this->nullable($name), $names))));
        if (count($names) > 50 || array_filter($names, static fn (string $name) => mb_strlen($name) > 100)) {
            return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $tags = [];
        foreach ($names as $name) {
            $tag = $entityManager->getRepository(Tag::class)->findOneBy(['tenant' => $product->getTenant(), 'name' => $name]);
            if (!$tag instanceof Tag) {
                $tag = new Tag($product->getTenant(), $name);
                $entityManager->persist($tag);
            } $tags[$name] = $tag;
        }
        $assignments = $entityManager->getRepository(ProductTag::class)->findBy(['product' => $product]);
        foreach ($assignments as $assignment) {
            if (!isset($tags[$assignment->getTag()->getName()])) {
                $entityManager->remove($assignment);
            } else {
                $assignment->claimSource('manual');
            }
        }
        $assigned = array_flip(array_map(static fn (ProductTag $assignment) => $assignment->getTag()->getName(), $assignments));
        foreach ($tags as $name => $tag) {
            if (!isset($assigned[$name])) {
                $entityManager->persist(new ProductTag($product, $tag));
            }
        }
        $entityManager->flush();

        return $this->json(['tags' => array_map(static fn (Tag $tag) => ['id' => $tag->getId()->toRfc4122(), 'name' => $tag->getName()], array_values($tags))]);
    }

    #[Route('/{id}/variant-options', methods: ['GET'])]
    public function variantOptions(
        string $id,
        EntityManagerInterface $entityManager,
        ProductVariantConfigurationReader $configuration,
    ): JsonResponse
    {
        $product = $this->product($id, $entityManager);

        return $this->json($configuration->read($product, $entityManager));
    }

    #[Route('/{id}/variant-options', methods: ['PUT'])]
    public function updateVariantOptions(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse
    {
        $product = $this->product($id, $entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $groups = $data['optionGroups'] ?? [];
        if (!is_array($groups)) {
            return $this->invalidVariantOptions($translator);
        }
        $validated = [];
        foreach ($groups as $groupData) {
            if (
                !is_array($groupData)
                || !is_string($groupData['propertyGroupId'] ?? null)
                || !Uuid::isValid($groupData['propertyGroupId'])
            ) {
                return $this->invalidVariantOptions($translator);
            }
            $propertyGroup = $entityManager->getRepository(PropertyGroup::class)->findOneBy([
                'id' => Uuid::fromString($groupData['propertyGroupId']),
                'tenant' => $product->getTenant(),
            ]);
            $propertyIds = $groupData['propertyIds'] ?? [];
            if (
                !$propertyGroup instanceof PropertyGroup
                || !is_array($propertyIds)
                || $propertyIds === []
                || isset($validated[$groupData['propertyGroupId']])
            ) {
                return $this->invalidVariantOptions($translator);
            }
            $properties = [];
            foreach ($propertyIds as $propertyId) {
                if (!is_string($propertyId) || !Uuid::isValid($propertyId)) {
                    return $this->invalidVariantOptions($translator);
                }
                $property = $entityManager->getRepository(Property::class)->findOneBy([
                    'id' => Uuid::fromString($propertyId),
                    'tenant' => $product->getTenant(),
                    'propertyGroup' => $propertyGroup,
                ]);
                if (!$property instanceof Property) {
                    return $this->invalidVariantOptions($translator);
                }
                $properties[$propertyId] = $property;
            }
            $validated[$groupData['propertyGroupId']] = [$propertyGroup, array_values($properties)];
        }

        return $entityManager->wrapInTransaction(function () use (
            $product,
            $entityManager,
            $validated,
        ): JsonResponse {
            $entityManager->lock($product, LockMode::PESSIMISTIC_WRITE);
            foreach ($entityManager->getRepository(ProductVariantOptionGroup::class)->findBy([
                'product' => $product,
            ]) as $old) {
                foreach ($entityManager->getRepository(ProductVariantOptionGroupProperty::class)->findBy([
                    'optionGroup' => $old,
                ]) as $oldValue) {
                    $entityManager->remove($oldValue);
                }
                $entityManager->remove($old);
            }
            $entityManager->flush();
            $payloadGroups = [];
            foreach (array_values($validated) as $position => [$propertyGroup, $properties]) {
                $optionGroup = new ProductVariantOptionGroup(
                    $product->getTenant(),
                    $product,
                    $propertyGroup,
                    $position,
                );
                $entityManager->persist($optionGroup);
                foreach ($properties as $property) {
                    $entityManager->persist(new ProductVariantOptionGroupProperty(
                        $product->getTenant(),
                        $optionGroup,
                        $property,
                    ));
                }
                $payloadGroups[] = $optionGroup;
            }
            $entityManager->flush();

            return $this->json([
                'optionGroups' => array_map(
                    fn (ProductVariantOptionGroup $group): array => $this->variantOptionGroupPayload($group, $entityManager),
                    $payloadGroups,
                ),
            ]);
        });
    }

    #[Route('/{id}/option-groups', methods: ['GET'])]
    public function optionGroups(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $product = $this->product($id, $entityManager);
        $groups = $entityManager->getRepository(ProductOptionGroup::class)->findBy(['product' => $product], ['position' => 'ASC']);

        return $this->json(['optionGroups' => array_map(fn (ProductOptionGroup $group) => $this->optionGroupPayload($group, $entityManager), $groups)]);
    }

    #[Route('/locales', methods: ['GET'], priority: 10)]
    public function locales(EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $enabled = array_flip($tenant->getEnabledSnippetLocales());
        $locales = array_values(array_filter(
            $entityManager->getRepository(Locale::class)->findBy(
                ['active' => true],
                ['code' => 'ASC'],
            ),
            static fn (Locale $locale): bool => isset($enabled[$locale->getCode()]),
        ));

        return $this->json([
            'locales' => array_map(
                static fn (Locale $locale): array => [
                    'id' => $locale->getId()->toRfc4122(),
                    'code' => $locale->getCode(),
                    'label' => self::localeLabel($locale->getCode()),
                ],
                $locales,
            ),
        ]);
    }

    #[Route('/currencies', methods: ['GET'], priority: 10)]
    public function currencies(EntityManagerInterface $entityManager): JsonResponse
    {
        $currencies = $entityManager->getRepository(Currency::class)->findBy([], ['code' => 'ASC']);

        return $this->json(['currencies' => array_map(static fn (Currency $currency) => ['code' => $currency->getCode(), 'symbol' => $currency->getSymbol(), 'decimalPrecision' => $currency->getDecimalPrecision()], $currencies)]);
    }

    #[Route('/{id}/translations/{localeCode}', methods: ['GET', 'PUT'])]
    public function translation(string $id, string $localeCode, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $product = $this->product($id, $entityManager, $request->isMethod('PUT'));
        $locale = $entityManager->getRepository(Locale::class)->findOneBy(['code' => $localeCode, 'active' => true]);
        if (!$locale instanceof Locale) {
            throw $this->createNotFoundException();
        }
        $translation = $entityManager->getRepository(ProductTranslation::class)->findOneBy(['product' => $product, 'locale' => $locale]);
        if ($request->isMethod('GET')) {
            return $this->json(['translation' => $translation ? $this->translationPayload($translation) : null]);
        }
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $name = $this->nullable($data['name'] ?? null);
        if ($name === null) {
            return $this->validation(
                $translator,
                ['name' => 'product.field.name'],
                true,
            );
        }
        $translation ??= new ProductTranslation($product, $locale, $name);
        $translation->update(
            $name,
            $this->nullable($data['shortDescription'] ?? null),
            $this->nullable($data['description'] ?? null),
            $this->nullable($data['metaTitle'] ?? null),
            $this->nullable($data['metaDescription'] ?? null),
            $this->nullable($data['metaKeywords'] ?? null),
            is_array($data['customFields'] ?? null)
                ? $data['customFields']
                : $translation->getCustomFields(),
        );
        $entityManager->persist($translation);
        $this->seoUrls->syncCanonical(
            $product->getTenant(),
            SeoUrlService::ENTITY_PRODUCT,
            $product->getId(),
            $locale,
            $name,
            $this->nullable($data['seoUrl'] ?? null),
        );
        $entityManager->flush();

        return $this->json(['translation' => $this->translationPayload($translation)]);
    }

    #[Route('/{id}/properties', methods: ['GET'])]
    public function productProperties(
        string $id,
        EntityManagerInterface $entityManager,
        PropertyValueReader $values,
    ): JsonResponse
    {
        $product = $this->product($id, $entityManager);
        $assignments = $entityManager->createQueryBuilder()
            ->select('assignment', 'property')
            ->from(ProductPropertyAssignment::class, 'assignment')
            ->join('assignment.property', 'property')
            ->where('assignment.product = :product')
            ->andWhere('property.tenant = :tenant')
            ->setParameter('product', $product)
            ->setParameter('tenant', $product->getTenant())
            ->getQuery()
            ->getResult();
        $properties = array_map(
            static fn (ProductPropertyAssignment $assignment): Property => $assignment->getProperty(),
            $assignments,
        );

        return $this->json([
            'propertyIds' => array_map(static fn (Property $property): string => $property->getId()->toRfc4122(), $properties),
            'properties' => $values->payloads($properties, $entityManager),
        ]);
    }

    #[Route('/{id}/categories', methods: ['GET'])]
    public function categories(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $product = $this->product($id, $entityManager);
        $assignments = $entityManager->getRepository(CategoryProduct::class)->findBy(
            ['product' => $product],
            ['position' => 'ASC'],
        );

        return $this->json([
            'categoryIds' => array_map(
                static fn (CategoryProduct $assignment) => $assignment->getCategory()->getId()->toRfc4122(),
                $assignments,
            ),
        ]);
    }

    #[Route('/{id}/categories', methods: ['PUT'])]
    public function updateCategories(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse
    {
        $product = $this->product($id, $entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $categoryIds = $data['categoryIds'] ?? [];
        if (!is_array($categoryIds) || count($categoryIds) !== count(array_unique($categoryIds))) {
            return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $categories = [];
        foreach ($categoryIds as $categoryId) {
            if (!is_string($categoryId) || !Uuid::isValid($categoryId)) {
                return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $category = $entityManager->getRepository(Category::class)->findOneBy([
                'id' => Uuid::fromString($categoryId),
                'tenant' => $product->getTenant(),
            ]);
            if (!$category instanceof Category) {
                return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $categories[] = $category;
        }

        $existingAssignments = $entityManager->getRepository(CategoryProduct::class)->findBy([
            'product' => $product,
        ]);
        $existingByCategoryId = [];
        foreach ($existingAssignments as $assignment) {
            $existingByCategoryId[$assignment->getCategory()->getId()->toRfc4122()] = $assignment;
        }
        foreach ($existingByCategoryId as $categoryId => $assignment) {
            if (!in_array($categoryId, $categoryIds, true)) {
                $entityManager->remove($assignment);
            } else {
                $assignment->claimSource('manual');
            }
        }
        foreach ($categories as $position => $category) {
            if (!isset($existingByCategoryId[$category->getId()->toRfc4122()])) {
                $entityManager->persist(new CategoryProduct($category, $product, $position));
            }
        }
        $entityManager->flush();

        return $this->json(['categoryIds' => $categoryIds]);
    }

    #[Route('/{id}/properties', methods: ['PUT'])]
    public function updateProductProperties(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $product = $this->product($id, $entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $propertyIds = $data['propertyIds'] ?? [];
        if (!is_array($propertyIds) || count($propertyIds) !== count(array_unique($propertyIds))) {
            return $this->json(['message' => $this->message($translator, 'property.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $properties = [];
        foreach ($propertyIds as $propertyId) {
            if (!is_string($propertyId) || !Uuid::isValid($propertyId)) {
                return $this->json(['message' => $this->message($translator, 'property.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $property = $entityManager->getRepository(Property::class)->findOneBy(['id' => Uuid::fromString($propertyId), 'tenant' => $product->getTenant()]);
            if (!$property instanceof Property) {
                return $this->json(['message' => $this->message($translator, 'property.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $properties[] = $property;
        }
        $existingAssignments = $entityManager->getRepository(ProductPropertyAssignment::class)->findBy(['product' => $product]);
        $existingByPropertyId = [];
        foreach ($existingAssignments as $assignment) {
            $existingByPropertyId[$assignment->getProperty()->getId()->toRfc4122()] = $assignment;
        }
        $requestedPropertyIds = array_map(static fn (Property $property) => $property->getId()->toRfc4122(), $properties);

        foreach ($existingByPropertyId as $propertyId => $assignment) {
            if (!in_array($propertyId, $requestedPropertyIds, true)) {
                $entityManager->remove($assignment);
            } else {
                $assignment->claimSource('manual');
            }
        }
        foreach ($properties as $property) {
            if (!isset($existingByPropertyId[$property->getId()->toRfc4122()])) {
                $entityManager->persist(new ProductPropertyAssignment($product->getTenant(), $product, $property));
            }
        }
        $entityManager->flush();

        return $this->json(['propertyIds' => array_map(static fn (Property $property) => $property->getId()->toRfc4122(), $properties)]);
    }

    #[Route('/{id}/extensions', methods: ['GET'])]
    public function extensions(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $product = $this->product($id, $entityManager);
        $items = $entityManager->getRepository(ProductExtensionValue::class)->findBy(['product' => $product], ['namespace' => 'ASC', 'position' => 'ASC']);

        return $this->json(['extensions' => array_map($this->extensionPayload(...), $items)]);
    }

    #[Route('/{id}/prices', methods: ['GET'])]
    public function prices(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $product = $this->product($id, $entityManager);
        $prices = $entityManager->getRepository(ProductPrice::class)->findBy(['product' => $product], ['createdAt' => 'ASC']);

        return $this->json(['prices' => array_map($this->pricePayload(...), $prices)]);
    }

    #[Route('/{id}/media', methods: ['GET'])]
    public function media(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $product = $this->product($id, $entityManager);
        $items = $entityManager->getRepository(ProductMedia::class)->findBy(['product' => $product], ['sortOrder' => 'ASC']);

        return $this->json(['media' => array_map($this->mediaPayload(...), $items)]);
    }

    #[Route('/{id}/downloads', methods: ['GET'])]
    public function downloads(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $product = $this->product($id, $entityManager);
        $downloads = $entityManager->getRepository(ProductDownload::class)->findBy(
            ['product' => $product],
            ['position' => 'ASC'],
        );

        return $this->json([
            'downloads' => array_map(
                static fn (ProductDownload $download): array => [
                    'id' => $download->getId()->toRfc4122(),
                    'title' => $download->getTitle(),
                    'position' => $download->getPosition(),
                    'fileName' => $download->getMedia()->getFileName(),
                    'mimeType' => $download->getMedia()->getMimeType(),
                    'url' => '/api/v1/media/'.$download->getMedia()->getId()->toRfc4122().'/file',
                ],
                $downloads,
            ),
        ]);
    }

    #[Route('/{id}/cross-sellings', methods: ['GET'])]
    public function crossSellings(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $product = $this->product($id, $entityManager);
        $groups = $entityManager->getRepository(ProductCrossSelling::class)->findBy(
            ['product' => $product],
            ['position' => 'ASC'],
        );

        return $this->json([
            'crossSellings' => array_map(
                fn (ProductCrossSelling $group): array => [
                    'id' => $group->getId()->toRfc4122(),
                    'name' => $group->getName(),
                    'type' => $group->getType(),
                    'active' => $group->isActive(),
                    'position' => $group->getPosition(),
                    'sourceProductStreamId' => $group->getSourceProductStreamId(),
                    'translations' => $this->crossSellingTranslations(
                        $group,
                        $entityManager,
                    ),
                    'products' => array_map(
                        fn (ProductCrossSellingAssignment $assignment): array => [
                            'id' => $assignment->getAssignedProduct()->getId()->toRfc4122(),
                            'name' => $this->defaultTranslation(
                                $assignment->getAssignedProduct(),
                            )?->getName() ?? '',
                            'position' => $assignment->getPosition(),
                        ],
                        $entityManager->getRepository(ProductCrossSellingAssignment::class)->findBy(
                            ['crossSelling' => $group],
                            ['position' => 'ASC'],
                        ),
                    ),
                ],
                $groups,
            ),
        ]);
    }

    #[Route('/{id}/media', methods: ['POST'])]
    public function uploadMedia(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $product = $this->product($id, $entityManager, true);
        $files = $request->files->get('files', []);
        if ($files instanceof UploadedFile) {
            $files = [$files];
        }
        if (!is_array($files) || $files === []) {
            return $this->json(['message' => $this->message($translator, 'product.media_required')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/avif' => 'avif'];
        $existingItems = $entityManager->getRepository(ProductMedia::class)->findBy(['product' => $product], ['sortOrder' => 'ASC']);
        $existingByName = [];
        foreach ($existingItems as $item) {
            if ($item->getFileName() !== '') {
                $existingByName[mb_strtolower($item->getFileName())] = $item;
            }
        }
        $duplicateAction = (string) $request->request->get('duplicateAction', '');
        if (!in_array($duplicateAction, ['', 'replace', 'rename'], true)) {
            return $this->json(['message' => $this->message($translator, 'product.media_invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $uploadFiles = [];
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid() || $file->getSize() === false || $file->getSize() > 10 * 1024 * 1024 || !isset($allowed[$file->getMimeType() ?? ''])) {
                return $this->json(['message' => $this->message($translator, 'product.media_invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $uploadFiles[] = [$file, $this->mediaFileName($file->getClientOriginalName(), $allowed[$file->getMimeType() ?? ''])];
        }
        $duplicates = array_values(array_unique(array_map(static fn (array $entry): string => $entry[1], array_filter($uploadFiles, fn (array $entry): bool => isset($existingByName[mb_strtolower($entry[1])])))));
        if ($duplicates !== [] && $duplicateAction === '') {
            return $this->json(['message' => $this->message($translator, 'product.media_duplicate'), 'duplicates' => $duplicates], Response::HTTP_CONFLICT);
        }
        $newItems = count(array_filter($uploadFiles, fn (array $entry): bool => !isset($existingByName[mb_strtolower($entry[1])]) || $duplicateAction === 'rename'));
        if (count($existingItems) + $newItems > 100) {
            return $this->json(['message' => $this->message($translator, 'product.media_limit')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $directory = $this->getParameter('kernel.project_dir')
            .'/var/media/'
            .$product->getTenant()->getId()->toRfc4122();
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create media directory.');
        }
        $items = [];
        $usedNames = array_fill_keys(array_keys($existingByName), true);
        $nextPosition = count($existingItems);
        foreach ($uploadFiles as [$file, $originalName]) {
            $extension = $allowed[$file->getMimeType() ?? ''];
            $fileSize = $file->getSize() ?: null;
            $mimeType = $file->getMimeType();
            $filename = Uuid::v7()->toRfc4122().'.'.$extension;
            $existingItem = $existingByName[mb_strtolower($originalName)] ?? null;
            $displayName = $existingItem instanceof ProductMedia
                && $duplicateAction === 'replace'
                ? $existingItem->getFileName()
                : $this->uniqueMediaFileName($originalName, $usedNames);
            $file->move($directory, $filename);
            $media = new Media(
                $product->getTenant(),
                $product->getTenant()->getId()->toRfc4122().'/'.$filename,
                $displayName,
                $extension,
                $fileSize,
                $mimeType,
                hash_file('sha256', $directory.'/'.$filename) ?: null,
            );
            $entityManager->persist($media);

            if ($existingItem instanceof ProductMedia && $duplicateAction === 'replace') {
                $existingItem->replaceMedia($media);
                $existingItem->claimSource('manual');
                $item = $existingItem;
            } else {
                $usedNames[mb_strtolower($displayName)] = true;
                $item = new ProductMedia(
                    $product->getTenant(),
                    $product,
                    $media,
                    $nextPosition++,
                );
                $entityManager->persist($item);
            }
            $items[] = $item;
        }
        $entityManager->flush();

        return $this->json(['media' => array_map($this->mediaPayload(...), $items)], Response::HTTP_CREATED);
    }

    #[Route('/{id}/media/{mediaId}/file', methods: ['GET'])]
    public function mediaFile(
        string $id,
        string $mediaId,
        EntityManagerInterface $entityManager,
        \App\Service\TenantMediaStorage $storage,
    ): BinaryFileResponse
    {
        $product = $this->product($id, $entityManager);
        if (!Uuid::isValid($mediaId)) {
            throw $this->createNotFoundException();
        }
        $item = $entityManager->getRepository(ProductMedia::class)->findOneBy(['id' => Uuid::fromString($mediaId), 'product' => $product]);
        if (!$item instanceof ProductMedia) {
            throw $this->createNotFoundException();
        }
        try {
            $path = $storage->path($item->getMedia(), $product->getTenant());
        } catch (\DomainException $exception) {
            throw $this->createNotFoundException('The media file is not available.', $exception);
        }

        return PrivateMediaResponse::inline($path, $item->getMedia());
    }

    #[Route('/{id}/media/order', methods: ['PUT'])]
    public function orderMedia(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $product = $this->product($id, $entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $ids = $data['mediaIds'] ?? [];
        if (!is_array($ids) || count($ids) !== count(array_unique($ids))) {
            return $this->json(['message' => $this->message($translator, 'product.media_order_invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $items = $entityManager->getRepository(ProductMedia::class)->findBy(['product' => $product], ['sortOrder' => 'ASC']);
        if (count($ids) !== count($items)) {
            return $this->json(['message' => $this->message($translator, 'product.media_order_invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $byId = [];
        foreach ($items as $item) {
            $byId[$item->getId()->toRfc4122()] = $item;
        }
        foreach ($ids as $mediaId) {
            if (!is_string($mediaId) || !isset($byId[$mediaId])) {
                return $this->json(['message' => $this->message($translator, 'product.media_order_invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }
        foreach ($items as $index => $item) {
            $item->setSortOrder(-$index - 1);
        }
        $entityManager->flush();
        foreach ($ids as $index => $mediaId) {
            $byId[$mediaId]->setSortOrder($index);
            $byId[$mediaId]->claimSource('manual');
        }
        $entityManager->flush();

        return $this->json(['media' => array_map($this->mediaPayload(...), array_map(fn (string $mediaId) => $byId[$mediaId], $ids))]);
    }

    #[Route('/{id}/media/{mediaId}', methods: ['DELETE'])]
    public function deleteMedia(string $id, string $mediaId, EntityManagerInterface $entityManager): Response
    {
        $product = $this->product($id, $entityManager, true);
        if (!Uuid::isValid($mediaId)) {
            throw $this->createNotFoundException();
        }
        $item = $entityManager->getRepository(ProductMedia::class)->findOneBy(['id' => Uuid::fromString($mediaId), 'product' => $product]);
        if (!$item instanceof ProductMedia) {
            throw $this->createNotFoundException();
        }
        $entityManager->remove($item);
        $entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/prices', methods: ['POST'])]
    public function createPrice(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $product = $this->product($id, $entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $currencyCode = strtoupper((string) ($data['currency'] ?? ''));
        $currency = $this->currency($currencyCode, $entityManager);
        $netAmount = $this->money($data['netAmount'] ?? null);
        $grossAmount = $this->money($data['grossAmount'] ?? null);
        $taxRate = $this->decimal($data['taxRate'] ?? null);
        $quantityStart = $this->decimal($data['quantityStart'] ?? '1');
        $quantityEnd = $this->nullable($data['quantityEnd'] ?? null);
        $priceType = (string) ($data['priceType'] ?? 'default');
        $pricingContext = $this->nullable($data['pricingContext'] ?? null) ?? 'default';
        if (
            !$currency instanceof Currency
            || $netAmount === null
            || $netAmount < 0
            || $grossAmount === null
            || $grossAmount < 0
            || $taxRate === null
            || $quantityStart === null
            || !in_array($priceType, ['default', 'purchase', 'list'], true)
        ) {
            $fields = [];
            if (!$currency instanceof Currency) {
                $fields['currency'] = 'product.field.currency';
            }
            if ($netAmount === null || $netAmount < 0) {
                $fields['netAmount'] = 'product.field.net_price';
            }
            if ($grossAmount === null || $grossAmount < 0) {
                $fields['grossAmount'] = 'product.field.gross_price';
            }
            if ($taxRate === null) {
                $fields['taxRate'] = 'product.field.tax_rate';
            }
            if ($quantityStart === null) {
                $fields['quantityStart'] = 'product.field.quantity_start';
            }

            return $this->validation(
                $translator,
                $fields,
            );
        }
        $entry = $this->priceEntry($currency, $grossAmount, $netAmount, (bool) ($data['linked'] ?? true), $this->optionalMoney($data['listGrossAmount'] ?? null), $this->optionalMoney($data['listNetAmount'] ?? null), (bool) ($data['listLinked'] ?? true), $this->optionalMoney($data['cheapestGrossAmount'] ?? null), $this->optionalMoney($data['cheapestNetAmount'] ?? null), (bool) ($data['cheapestLinked'] ?? true));
        $price = new ProductPrice($product->getTenant(), $product, $currency, $priceType, [$currency->getId()->toRfc4122() => $entry], $taxRate);
        $price->updateAdvanced($quantityStart, $quantityEnd, $pricingContext, $this->dateTime($data['validFrom'] ?? null), $this->dateTime($data['validUntil'] ?? null));
        $entityManager->persist($price);
        $entityManager->flush();

        return $this->json(['price' => $this->pricePayload($price)], Response::HTTP_CREATED);
    }

    #[Route('/{id}/prices/{priceId}', methods: ['PATCH'])]
    public function updatePrice(string $id, string $priceId, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $product = $this->product($id, $entityManager, true);
        if (!Uuid::isValid($priceId)) {
            throw $this->createNotFoundException();
        }
        $price = $entityManager->getRepository(ProductPrice::class)->findOneBy(['id' => Uuid::fromString($priceId), 'product' => $product]);
        if (!$price instanceof ProductPrice) {
            throw $this->createNotFoundException();
        }
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $netAmount = $this->money($data['netAmount'] ?? null);
        $grossAmount = $this->money($data['grossAmount'] ?? null);
        $taxRate = $this->decimal($data['taxRate'] ?? null);
        if (
            $netAmount === null
            || $netAmount < 0
            || $grossAmount === null
            || $grossAmount < 0
            || $taxRate === null
        ) {
            $fields = [];
            if ($netAmount === null || $netAmount < 0) {
                $fields['netAmount'] = 'product.field.net_price';
            }
            if ($grossAmount === null || $grossAmount < 0) {
                $fields['grossAmount'] = 'product.field.gross_price';
            }
            if ($taxRate === null) {
                $fields['taxRate'] = 'product.field.tax_rate';
            }

            return $this->validation(
                $translator,
                $fields,
            );
        }
        $price->updatePrice([$price->getCurrency()->getId()->toRfc4122() => $this->priceEntry($price->getCurrency(), $grossAmount, $netAmount, (bool) ($data['linked'] ?? true), $this->optionalMoney($data['listGrossAmount'] ?? null), $this->optionalMoney($data['listNetAmount'] ?? null), (bool) ($data['listLinked'] ?? true), $this->optionalMoney($data['cheapestGrossAmount'] ?? null), $this->optionalMoney($data['cheapestNetAmount'] ?? null), (bool) ($data['cheapestLinked'] ?? true))], $taxRate);
        $entityManager->flush();

        return $this->json(['price' => $this->pricePayload($price)]);
    }

    #[Route('/{id}/extensions', methods: ['POST'])]
    public function createExtension(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $product = $this->product($id, $entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $namespace = $this->nullable($data['namespace'] ?? null);
        $key = $this->nullable($data['key'] ?? null);
        if ($namespace === null || $key === null || !array_key_exists('value', $data)) {
            return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $position = $entityManager->getRepository(ProductExtensionValue::class)->count(['product' => $product]);
        $item = new ProductExtensionValue($product->getTenant(), $product, $namespace, $key, $data['value'], $position, (string) ($data['source'] ?? 'manual'));
        $entityManager->persist($item);
        $entityManager->flush();

        return $this->json(['extension' => $this->extensionPayload($item)], Response::HTTP_CREATED);
    }

    #[Route('/{id}/extensions/{extensionId}', methods: ['DELETE'])]
    public function deleteExtension(string $id, string $extensionId, EntityManagerInterface $entityManager): Response
    {
        $product = $this->product($id, $entityManager, true);
        if (!Uuid::isValid($extensionId)) {
            throw $this->createNotFoundException();
        }
        $item = $entityManager->getRepository(ProductExtensionValue::class)->findOneBy(['id' => Uuid::fromString($extensionId), 'product' => $product]);
        if (!$item instanceof ProductExtensionValue) {
            throw $this->createNotFoundException();
        }
        $entityManager->remove($item);
        $entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/option-groups', methods: ['POST'])]
    public function createOptionGroup(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $product = $this->product($id, $entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $name = $this->nullable($data['name'] ?? null);
        $values = array_values(array_unique(array_filter(array_map($this->nullable(...), (array) ($data['values'] ?? [])))));
        if ($name === null || $values === []) {
            return $this->json(['message' => $this->message($translator, 'product.option_group_invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($entityManager->getRepository(ProductOptionGroup::class)->findOneBy(['product' => $product, 'name' => $name]) instanceof ProductOptionGroup) {
            return $this->json(['message' => $this->message($translator, 'product.option_group_used')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $position = count($entityManager->getRepository(ProductOptionGroup::class)->findBy(['product' => $product]));
        $group = new ProductOptionGroup($product->getTenant(), $product, $name, $position);
        $entityManager->persist($group);
        foreach ($values as $index => $value) {
            $entityManager->persist(new ProductOptionValue($product->getTenant(), $group, $value, $index));
        }
        $entityManager->flush();

        return $this->json(['optionGroup' => $this->optionGroupPayload($group, $entityManager)], Response::HTTP_CREATED);
    }

    #[Route('/{id}/variants', methods: ['POST'])]
    public function createVariant(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $product = $this->product($id, $entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $sku = $this->nullable($data['sku'] ?? null);
        if ($sku === null) {
            return $this->json(['message' => $this->message($translator, 'product.sku_required')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($entityManager->getRepository(Product::class)->findOneBy(['tenant' => $product->getTenant(), 'sku' => $sku]) instanceof Product) {
            return $this->json(['message' => $this->message($translator, 'product.sku_used')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $parentTranslation = $this->defaultTranslation($product);
        $variantName = $this->nullable($data['name'] ?? null) ?? $parentTranslation?->getName() ?? $sku;
        $variant = new Product($product->getTenant());
        $variant->makeChildOf($product, $sku, $this->nullable($data['ean'] ?? null), $this->options($data['optionValues'] ?? []));
        $entityManager->persist($variant);
        $entityManager->persist($this->newDefaultTranslation($variant, $variantName));
        $entityManager->flush();

        return $this->json(['variant' => $this->variantPayload($variant)], Response::HTTP_CREATED);
    }

    #[Route('/{id}/variants/generate', methods: ['POST'])]
    public function generateVariants(
        string $id,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        ProductVariantConfigurationReader $configuration,
    ): JsonResponse
    {
        $product = $this->product($id, $entityManager, true);
        return $entityManager->wrapInTransaction(function () use (
            $product,
            $entityManager,
            $translator,
            $configuration,
        ): JsonResponse {
            // Serialize generation for this parent before reading existing combinations.
            $entityManager->lock($product, LockMode::PESSIMISTIC_WRITE);
            return $this->generateMissingVariants($product, $entityManager, $translator, $configuration);
        });
    }

    private function generateMissingVariants(
        Product $product,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        ProductVariantConfigurationReader $configuration,
    ): JsonResponse
    {
        $groups = $entityManager->getRepository(ProductVariantOptionGroup::class)->findBy(
            ['product' => $product],
            ['position' => 'ASC'],
        );
        $sets = [];
        $propertiesById = [];
        $combinationCount = 1;
        foreach ($groups as $group) {
            $values = $entityManager->getRepository(ProductVariantOptionGroupProperty::class)->findBy([
                'optionGroup' => $group,
            ]);
            if ($values === []) {
                return $this->invalidVariantOptions($translator);
            }
            $propertyIds = [];
            foreach ($values as $value) {
                $property = $value->getProperty();
                $propertiesById[$property->getId()->toRfc4122()] = $property;
                $propertyIds[] = $property->getId()->toRfc4122();
            }
            $propertyIds = array_values(array_unique($propertyIds));
            $combinationCount *= count($propertyIds);
            if ($combinationCount > 200) {
                return $this->json([
                    'message' => $this->message($translator, 'product.too_many_variants'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $sets[] = [(string) $group->getPropertyGroup()->getId(), $propertyIds];
        }
        if ($sets === []) {
            return $this->invalidVariantOptions($translator);
        }

        $combinations = $this->combinations($sets);
        $desired = [];
        foreach ($combinations as $options) {
            $ids = array_values($options);
            sort($ids);
            $desired[implode(':', $ids)] = $options;
        }
        $existing = [];
        $wildcards = [];
        foreach ($configuration->read($product, $entityManager)['existingCombinations'] as $variant) {
            if ($variant['wildcardGroupIds'] !== []) {
                $wildcards[] = $variant;
            }
            if ($variant['propertyIds'] !== []) {
                $existing[implode(':', $variant['propertyIds'])] = true;
            }
        }
        $created = 0;
        $skipped = 0;
        $sequence = 0;
        foreach ($desired as $key => $options) {
            $covered = false;
            foreach ($wildcards as $variant) {
                if (count($options) !== count($variant['propertyIds']) + count($variant['wildcardGroupIds'])) {
                    continue;
                }
                $covered = true;
                foreach ($options as $groupId => $propertyId) {
                    if (!in_array($groupId, $variant['wildcardGroupIds'], true) && !in_array($propertyId, $variant['propertyIds'], true)) {
                        $covered = false;
                        break;
                    }
                }
                if ($covered) {
                    break;
                }
            }
            if (isset($existing[$key]) || $covered) {
                $skipped++;
                continue;
            }
            do {
                $sequence++;
                $sku = ($product->getSku() ?? 'product').'.'.$sequence;
            } while ($entityManager->getRepository(Product::class)->findOneBy([
                'tenant' => $product->getTenant(),
                'sku' => $sku,
            ]) instanceof Product);
            $labels = [];
            foreach ($options as $propertyId) {
                $labels[] = $propertiesById[$propertyId]->getName();
            }
            $parentTranslation = $this->defaultTranslation($product);
            $variant = new Product($product->getTenant());
            $variant->inheritCatalogDataFrom($product);
            $variant->makeChildOf($product, $sku, null, $options);
            $entityManager->persist($variant);
            $entityManager->persist($this->newDefaultTranslation(
                $variant,
                trim(($parentTranslation?->getName() ?? $sku).' '.implode(' / ', $labels)),
            ));
            $this->copyMedia($product, $variant, $entityManager);
            foreach ($options as $propertyId) {
                $entityManager->persist(new ProductVariantOptionValue(
                    $product->getTenant(),
                    $variant,
                    $propertiesById[$propertyId],
                ));
            }
            $created++;
        }
        $entityManager->flush();

        return $this->json([
            'message' => $this->message($translator, 'product.variants_generated'),
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    private function invalidVariantOptions(TranslatorInterface $translator): JsonResponse
    {
        return $this->json([
            'message' => $this->message($translator, 'product.option_group_invalid'),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('/{id}/variants', methods: ['DELETE'])]
    public function deleteVariants(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): Response|JsonResponse
    {
        $product = $this->product($id, $entityManager, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $variantIds = $data['variantIds'] ?? [];
        if (!is_array($variantIds) || $variantIds === []) {
            return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        foreach (array_unique($variantIds) as $variantId) {
            if (!is_string($variantId) || !Uuid::isValid($variantId)) {
                return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $variant = $entityManager->getRepository(Product::class)->findOneBy(['id' => Uuid::fromString($variantId), 'parent' => $product]);
            if (!$variant instanceof Product) {
                return $this->json(['message' => $this->message($translator, 'product.invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $this->seoUrls->removeForEntity(
                $variant->getTenant(),
                SeoUrlService::ENTITY_PRODUCT,
                $variant->getId(),
            );
            $entityManager->remove($variant);
        }
        $entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function product(string $id, EntityManagerInterface $entityManager, bool $ownerRequired = false): Product
    {
        $tenant = $this->tenant($entityManager, $ownerRequired);
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $product = $entityManager->getRepository(Product::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant]);
        if (!$product instanceof Product) {
            throw $this->createNotFoundException();
        }

        return $product;
    }

    private function tenant(EntityManagerInterface $entityManager, bool $ownerRequired = false): Tenant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $membership = $entityManager->getRepository(TenantMembership::class)->forUser($user);
        if (!$membership instanceof TenantMembership || ($ownerRequired && $membership->getRole() !== 'owner')) {
            throw $this->createAccessDeniedException();
        }

        return $membership->getTenant();
    }

    private function defaultLocale(Tenant $tenant, EntityManagerInterface $entityManager): Locale
    {
        $locale = $entityManager->getRepository(Locale::class)->findOneBy([
            'code' => $tenant->getDefaultSnippetLocale(),
            'active' => true,
        ]);
        if (!$locale instanceof Locale) {
            throw new \LogicException('The configured default snippet locale does not exist.');
        }

        return $locale;
    }

    private function defaultTranslation(Product $product): ?ProductTranslation
    {
        $locale = $this->entityManager->getRepository(Locale::class)->findOneBy([
            'code' => $product->getTenant()->getDefaultSnippetLocale(),
            'active' => true,
        ]);
        if ($locale instanceof Locale) {
            $translation = $this->entityManager->getRepository(ProductTranslation::class)->findOneBy([
                'product' => $product,
                'locale' => $locale,
            ]);
            if ($translation instanceof ProductTranslation) {
                return $translation;
            }
        }

        return $this->entityManager->getRepository(ProductTranslation::class)->findOneBy([
            'product' => $product,
        ]);
    }

    private function newDefaultTranslation(Product $product, string $name): ProductTranslation
    {
        $locale = $this->defaultLocale($product->getTenant(), $this->entityManager);
        $translation = new ProductTranslation(
            $product,
            $locale,
            $name,
        );
        $translation->update(
            $name,
            null,
            null,
            null,
            null,
            null,
        );
        $this->seoUrls->syncCanonical(
            $product->getTenant(),
            SeoUrlService::ENTITY_PRODUCT,
            $product->getId(),
            $locale,
            $name,
        );

        return $translation;
    }

    private function currency(string $code, EntityManagerInterface $entityManager): ?Currency
    {
        $currency = $entityManager->getRepository(Currency::class)->findOneBy(['code' => $code]);
        if ($currency instanceof Currency) {
            return $currency;
        }
        $knownCurrencies = ['BAM' => ['KM', 2], 'EUR' => ['€', 2], 'USD' => ['$', 2], 'GBP' => ['£', 2], 'TRY' => ['₺', 2], 'CHF' => ['CHF', 2]];
        if (!isset($knownCurrencies[$code])) {
            return null;
        }
        [$symbol, $precision] = $knownCurrencies[$code];
        $currency = new Currency($code, $symbol, $precision);
        $entityManager->persist($currency);

        return $currency;
    }

    /** @return array<string, string> */
    private function options(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

return array_filter($value, static fn ($item): bool => is_string($item));
    }
    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
    private function decimal(mixed $value): ?string
    {
        $value = $this->nullable($value);

        return $value !== null && preg_match('/^\d+(\.\d{1,4})?$/', $value) ? $value : null;
    }
    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        } $value = filter_var($value, FILTER_VALIDATE_INT);

        return $value === false ? null : $value;
    }
    private function money(mixed $value): ?int
    {
        $value = str_replace(',', '.', trim((string) $value));
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            return null;
        }

return (int) round((float) $value * 100);
    }
    private function optionalMoney(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : $this->money($value);
    }
    private function priceEntry(Currency $currency, ?int $gross, ?int $net, bool $linked, ?int $listGross = null, ?int $listNet = null, bool $listLinked = true, ?int $regulationGross = null, ?int $regulationNet = null, bool $regulationLinked = true): ?array
    {
        if ($gross === null && $net === null) {
            return null;
        }
        $currencyId = $currency->getId()->toRfc4122();
        $entry = ['currencyId' => $currencyId, 'currencyCode' => $currency->getCode(), 'gross' => ($gross ?? 0) / 100, 'net' => ($net ?? 0) / 100, 'linked' => $linked];
        if ($listGross !== null || $listNet !== null) {
            $entry['listPrice'] = ['currencyId' => $currencyId, 'currencyCode' => $currency->getCode(), 'gross' => ($listGross ?? 0) / 100, 'net' => ($listNet ?? 0) / 100, 'linked' => $listLinked];
        }
        if ($regulationGross !== null || $regulationNet !== null) {
            $entry['regulationPrice'] = ['currencyId' => $currencyId, 'currencyCode' => $currency->getCode(), 'gross' => ($regulationGross ?? 0) / 100, 'net' => ($regulationNet ?? 0) / 100, 'linked' => $regulationLinked];
        }

        return $entry;
    }
    private function cents(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value * 100) : null;
    }
    private function firstPrice(array $collection): ?array
    {
        foreach ($collection as $entry) {
            if (is_array($entry)) {
                return $entry;
            }
        }

return null;
    }
    private function legacyRegularPrice(Product $product): array
    {
        $price = $this->firstPrice($product->getPrice()) ?? [];
        $purchase = $this->firstPrice($product->getPurchasePrice()) ?? [];
        $list = is_array($price['listPrice'] ?? null) ? $price['listPrice'] : [];

        return ['currency' => is_string($price['currencyCode'] ?? null) ? $price['currencyCode'] : null, 'taxRate' => $product->getTax()?->getRate(), 'grossAmount' => $this->cents($price['gross'] ?? null), 'netAmount' => $this->cents($price['net'] ?? null), 'purchaseGrossAmount' => $this->cents($purchase['gross'] ?? null), 'purchaseNetAmount' => $this->cents($purchase['net'] ?? null), 'listGrossAmount' => $this->cents($list['gross'] ?? null), 'listNetAmount' => $this->cents($list['net'] ?? null), 'linked' => (bool) ($price['linked'] ?? true), 'purchaseLinked' => (bool) ($purchase['linked'] ?? true), 'listLinked' => (bool) ($list['linked'] ?? true)];
    }
    private function date(mixed $value): ?\DateTimeImmutable
    {
        $value = $this->nullable($value);
        if ($value === null) {
            return null;
        } try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
    private function dateTime(mixed $value): ?\DateTimeImmutable
    {
        return $this->date($value);
    }
    private function data(Request $request, TranslatorInterface $translator): array|JsonResponse
    {
        try {
            return $request->toArray();
        } catch (\JsonException) {
            return $this->json(['message' => $this->message($translator, 'product.json_required')], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * @param array<string, string> $fields Maps the UI field path to its label translation key.
     */
    private function validation(
        TranslatorInterface $translator,
        array $fields,
        bool $required = false,
    ): JsonResponse {
        $user = $this->getUser();
        $locale = $user instanceof User ? $user->getLocale() : 'bs';
        $labels = [];
        $errors = [];
        $fieldMessageKey = $required
            ? 'product.validation.required_field'
            : 'product.validation.invalid_field';

        foreach ($fields as $field => $labelKey) {
            $labels[] = $translator->trans($labelKey, locale: $locale);
            $errors[$field] = $translator->trans($fieldMessageKey, locale: $locale);
        }

        return $this->json([
            'message' => $translator->trans(
                $required
                    ? 'product.validation.required_fields'
                    : 'product.validation.invalid_fields',
                ['%fields%' => implode(', ', $labels)],
                locale: $locale,
            ),
            'errors' => $errors,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function message(TranslatorInterface $translator, string $key): string
    {
        $user = $this->getUser();

        return $translator->trans($key, locale: $user instanceof User ? $user->getLocale() : 'bs');
    }
    /**
     * @param list<Product> $products
     *
     * @return array<string, ProductMedia>
     */
    private function coverMediaByProductId(array $products): array
    {
        if ($products === []) {
            return [];
        }

        $mediaItems = $this->entityManager->createQuery(
            'SELECT productMedia
            FROM App\\Entity\\ProductMedia productMedia
            WHERE productMedia.product IN (:products)
            AND productMedia.tenant = :tenant
            AND productMedia.sortOrder = (
                SELECT MIN(candidate.sortOrder)
                FROM App\\Entity\\ProductMedia candidate
                WHERE candidate.product = productMedia.product
                AND candidate.tenant = :tenant
            )',
        )
            ->setParameter('products', $products)
            ->setParameter('tenant', $products[0]->getTenant())
            ->getResult();

        $covers = [];
        foreach ($mediaItems as $media) {
            if ($media instanceof ProductMedia) {
                $covers[$media->getProduct()->getId()->toRfc4122()] = $media;
            }
        }

        return $covers;
    }

    /**
     * @param list<Product> $products
     *
     * @return array<string, true>
     */
    private function productIdsWithVariants(array $products): array
    {
        if ($products === []) {
            return [];
        }

        $rows = $this->entityManager->createQuery(
            'SELECT IDENTITY(variant.parent) AS parentId
            FROM App\\Entity\\Product variant
            WHERE variant.parent IN (:products)
            GROUP BY variant.parent',
        )
            ->setParameter('products', $products)
            ->getScalarResult();

        $ids = [];
        foreach ($rows as $row) {
            $parentId = $row['parentId'] ?? null;
            if (is_string($parentId)) {
                $ids[$parentId] = true;
            }
        }

        return $ids;
    }

    private function productListTotal(
        Tenant $tenant,
        string $search,
        string $status,
        string $categoryId = '',
        string $manufacturerId = '',
        bool $includeVariants = false,
    ): int
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT product.id)')
            ->from(Product::class, 'product')
            ->where('product.tenant = :tenant')
            ->setParameter('tenant', $tenant);

        if (!$includeVariants) {
            $queryBuilder->andWhere('product.parent IS NULL');
        }

        if (Uuid::isValid($categoryId)) {
            $queryBuilder
                ->andWhere(
                    'EXISTS (SELECT 1 FROM '.CategoryProduct::class.' categoryProduct '
                    .'WHERE categoryProduct.product = product AND IDENTITY(categoryProduct.category) = :categoryId)'
                )
                ->setParameter('categoryId', Uuid::fromString($categoryId));
        }

        if (Uuid::isValid($manufacturerId)) {
            $queryBuilder
                ->andWhere('IDENTITY(product.manufacturer) = :manufacturerId')
                ->setParameter('manufacturerId', Uuid::fromString($manufacturerId));
        }

        if (in_array($status, ['draft', 'active', 'archived'], true)) {
            $queryBuilder
                ->andWhere('product.status = :status')
                ->setParameter('status', $status);
        }

        if ($search !== '') {
            $queryBuilder
                ->andWhere(
                    'LOWER(product.sku) LIKE :search OR EXISTS ('
                    .'SELECT 1 FROM '.ProductTranslation::class.' searchTranslation '
                    .'WHERE searchTranslation.product = product AND LOWER(searchTranslation.name) LIKE :search'
                    .') OR EXISTS ('
                    .'SELECT 1 FROM '.ProductTranslation::class.' parentSearchTranslation '
                    .'WHERE parentSearchTranslation.product = product.parent AND LOWER(parentSearchTranslation.name) LIKE :search'
                    .')'
                )
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    private function listPayload(
        Product $product,
        ?ProductMedia $cover,
        bool $hasVariants,
        float $stock,
    ): array
    {
        $payload = $this->payload($product);
        $payload['coverUrl'] = $cover instanceof ProductMedia
            ? '/api/v1/products/'.$product->getId()->toRfc4122().'/media/'.$cover->getId()->toRfc4122().'/file'
            : null;
        $payload['hasVariants'] = $hasVariants;
        $regularPrice = $this->legacyRegularPrice($product);
        $payload['listingPrice'] = [
            'grossAmount' => $regularPrice['grossAmount'],
            'currency' => $regularPrice['currency'],
        ];
        $payload['stock'] = $stock;

        return $payload;
    }

    /** @param list<Product> $products
     *
     * @return array<string, float>
     */
    private function stockByProductId(array $products): array
    {
        if ($products === []) {
            return [];
        }

        $stockByProductId = [];
        foreach ($this->entityManager->getRepository(InventoryLevel::class)->findBy([
            'tenant' => $products[0]->getTenant(),
            'product' => $products,
        ]) as $level) {
            $productId = $level->getProduct()->getId()->toRfc4122();
            $stockByProductId[$productId] = ($stockByProductId[$productId] ?? 0) + (float) $level->getAvailableQuantity();
        }

        return $stockByProductId;
    }

    /**
     * @param list<Product> $products
     *
     * @return list<array{id: string, name: string, sku: ?string, unitCode: ?string, variantCombination: ?string}>
     */
    private function optionPayloads(array $products, Tenant $tenant): array
    {
        if ($products === []) {
            return [];
        }

        $locale = $this->defaultLocale($tenant, $this->entityManager);
        $translationProducts = $products;
        foreach ($products as $product) {
            if ($product->getParent() instanceof Product) {
                $translationProducts[] = $product->getParent();
            }
        }
        $namesByProductId = [];
        foreach ($this->entityManager->getRepository(ProductTranslation::class)->findBy([
            'product' => $translationProducts,
            'locale' => $locale,
        ]) as $translation) {
            $namesByProductId[$translation->getProduct()->getId()->toRfc4122()] = $translation->getName();
        }
        $optionValueLabels = $this->optionValueLabels($products);

        return array_map(
            static function (Product $product) use ($namesByProductId, $optionValueLabels): array {
                $productId = $product->getId()->toRfc4122();
                $parent = $product->getParent();
                $name = $namesByProductId[$productId] ?? '';
                $variantCombination = null;

                if ($parent instanceof Product) {
                    $name = $namesByProductId[$parent->getId()->toRfc4122()] ?? $name;
                    $values = array_values($product->getOptionValues());
                    $values = array_values(array_filter($values, static fn (mixed $value): bool => is_scalar($value) && (string) $value !== ''));
                    $variantCombination = $values === [] ? null : implode(' / ', array_map(
                        static fn (mixed $value): string => $optionValueLabels[(string) $value] ?? (string) $value,
                        $values,
                    ));
                }

                return [
                    'id' => $productId,
                    'name' => $name,
                    'sku' => $product->getSku(),
                    'unitCode' => $product->getUnit()?->getCode(),
                    'variantCombination' => $variantCombination,
                ];
            },
            $products,
        );
    }

    /**
     * @param list<Product> $products
     *
     * @return array<string, string>
     */
    private function optionValueLabels(array $products): array
    {
        $ids = [];
        foreach ($products as $product) {
            if (!$product->getParent() instanceof Product) {
                continue;
            }
            foreach (array_values($product->getOptionValues()) as $value) {
                if (is_scalar($value) && Uuid::isValid((string) $value)) {
                    $ids[(string) $value] = Uuid::fromString((string) $value);
                }
            }
        }
        if ($ids === []) {
            return [];
        }

        $labels = [];
        foreach ($this->entityManager->getRepository(Property::class)->findBy(['id' => array_values($ids)]) as $property) {
            $labels[$property->getId()->toRfc4122()] = $property->getName();
        }
        foreach ($this->entityManager->getRepository(ProductOptionValue::class)->findBy(['id' => array_values($ids)]) as $optionValue) {
            $labels[$optionValue->getId()->toRfc4122()] = $optionValue->getValue();
        }

        return $labels;
    }

    private function payload(Product $product): array
    {
        $translation = $this->defaultTranslation($product);
        $seoPaths = $this->seoUrls->canonicalPaths(
            $product->getTenant(),
            SeoUrlService::ENTITY_PRODUCT,
            $product->getId(),
        );
        $translations = [];
        foreach ($this->entityManager->getRepository(ProductTranslation::class)->findBy(['product' => $product]) as $item) {
            $translations[$item->getLocale()->getCode()] = [
                'name' => $item->getName(),
                'seoUrl' => $seoPaths[$item->getLocale()->getCode()] ?? null,
            ];
        }

        return [
            'id' => $product->getId()->toRfc4122(),
            'parentId' => $product->getParent()?->getId()->toRfc4122(),
            'name' => $translation?->getName() ?? '',
            'seoUrl' => $translation instanceof ProductTranslation
                ? $seoPaths[$translation->getLocale()->getCode()] ?? ''
                : '',
            'translations' => $translations,
            'sku' => $product->getSku(),
            'ean' => $product->getEan(),
            'optionValues' => $product->getOptionValues(),
            'attributeConfiguration' => $product->getAttributeConfiguration(),
            'productType' => $product->getProductType(),
            'manufacturerNumber' => $product->getManufacturerNumber(),
            'shippingClass' => $product->getShippingClass(),
            'deliveryTime' => $product->getDeliveryTime(),
            'packUnit' => $product->getPackUnit(),
            'packUnitPlural' => $product->getPackUnitPlural(),
            'releaseDate' => $product->getReleaseDate()?->format(DATE_ATOM),
            'isFeatured' => $product->isFeatured(),
            'visibility' => $product->getVisibility(),
            'minPurchaseQuantity' => $this->displayDecimal($product->getMinPurchaseQuantity()),
            'purchaseSteps' => $this->displayDecimal($product->getPurchaseSteps()),
            'maxPurchaseQuantity' => $product->getMaxPurchaseQuantity(),
            'restockTimeDays' => $product->getRestockTimeDays(),
            'clearanceSale' => $product->isClearanceSale(),
            'freeShipping' => $product->isFreeShipping(),
            'searchKeywords' => $product->getSearchKeywords(),
            'weightGrams' => $product->getWeightGrams(),
            'lengthMillimeters' => $product->getLengthMillimeters(),
            'widthMillimeters' => $product->getWidthMillimeters(),
            'heightMillimeters' => $product->getHeightMillimeters(),
            'price' => $product->getPrice(),
            'purchasePrice' => $product->getPurchasePrice(),
            'cheapestPrice' => $product->getCheapestPrice(),
            'regularPrice' => $this->legacyRegularPrice($product),
            'shortDescription' => $translation?->getShortDescription(),
            'description' => $translation?->getDescription(),
            'status' => $product->getStatus(),
            'manufacturer' => $this->manufacturerName($product->getManufacturer()),
            'createdAt' => $product->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $product->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
    private function displayDecimal(?string $value): ?string
    {
        return $value === null ? null : rtrim(rtrim($value, '0'), '.');
    }

    private function manufacturerName(?Manufacturer $manufacturer): ?string
    {
        if (!$manufacturer instanceof Manufacturer) {
            return null;
        }
        $locale = $this->entityManager->getRepository(Locale::class)->findOneBy([
            'code' => $manufacturer->getTenant()->getDefaultSnippetLocale(),
            'active' => true,
        ]);
        $translation = $locale instanceof Locale
            ? $this->entityManager->getRepository(ManufacturerTranslation::class)->findOneBy([
                'manufacturer' => $manufacturer,
                'locale' => $locale,
            ])
            : null;

        return $translation instanceof ManufacturerTranslation ? $translation->getName() : null;
    }
    /** @param list<Product> $variants */
    private function variantPayloads(array $variants, Product $parent): array
    {
        // Only the requested page is enriched, in two batched queries.
        $covers = $this->coverMediaByProductId([$parent, ...$variants]);
        $stock = $this->stockByProductId($variants);
        $parentCover = $covers[$parent->getId()->toRfc4122()] ?? null;

        return array_map(function (Product $variant) use ($covers, $stock, $parentCover): array {
            $id = $variant->getId()->toRfc4122();
            $cover = $covers[$id] ?? $parentCover;
            $price = $this->legacyRegularPrice($variant);

            return [
                'id' => $id,
                'sku' => $variant->getSku(),
                'ean' => $variant->getEan(),
                'name' => $this->defaultTranslation($variant)?->getName() ?? '',
                'price' => $price['grossAmount'],
                'currency' => $price['currency'],
                'coverUrl' => $cover instanceof ProductMedia
                    ? '/api/v1/products/'.$cover->getProduct()->getId()->toRfc4122()
                        .'/media/'.$cover->getId()->toRfc4122().'/file'
                    : null,
                'stock' => $stock[$id] ?? 0,
                'optionValues' => $variant->getOptionValues(),
                'status' => $variant->getStatus(),
            ];
        }, $variants);
    }
    private function optionGroupPayload(ProductOptionGroup $group, EntityManagerInterface $entityManager): array
    {
        $values = $entityManager->getRepository(ProductOptionValue::class)->findBy(['optionGroup' => $group], ['position' => 'ASC']);

        return ['id' => $group->getId()->toRfc4122(), 'name' => $group->getName(), 'values' => array_map(static fn (ProductOptionValue $value) => ['id' => $value->getId()->toRfc4122(), 'value' => $value->getValue()], $values)];
    }
    private function variantOptionGroupPayload(ProductVariantOptionGroup $group, EntityManagerInterface $entityManager): array
    {
        $properties = $entityManager->getRepository(ProductVariantOptionGroupProperty::class)->findBy(['optionGroup' => $group]);

        return ['id' => $group->getId()->toRfc4122(), 'propertyGroupId' => $group->getPropertyGroup()->getId()->toRfc4122(), 'name' => $group->getPropertyGroup()->getName(), 'propertyIds' => array_map(static fn (ProductVariantOptionGroupProperty $item) => $item->getProperty()->getId()->toRfc4122(), $properties)];
    }
    private function extensionPayload(ProductExtensionValue $item): array
    {
        return ['id' => $item->getId()->toRfc4122(), 'namespace' => $item->getNamespace(), 'key' => $item->getKey(), 'value' => $item->getValue()];
    }

    /** @return array<string, string> */
    private function crossSellingTranslations(
        ProductCrossSelling $group,
        EntityManagerInterface $entityManager,
    ): array {
        $translations = [];
        foreach ($entityManager->getRepository(ProductCrossSellingTranslation::class)
            ->findBy(['crossSelling' => $group]) as $translation) {
            $translations[$translation->getLocale()->getCode()] = $translation->getName();
        }

        return $translations;
    }
    private function mediaPayload(ProductMedia $item): array
    {
        return ['id' => $item->getId()->toRfc4122(), 'type' => $item->getMediaType(), 'url' => '/api/v1/products/'.$item->getProduct()->getId()->toRfc4122().'/media/'.$item->getId()->toRfc4122().'/file', 'fileName' => $item->getFileName(), 'fileExtension' => $item->getFileExtension(), 'fileSize' => $item->getFileSize(), 'mimeType' => $item->getMimeType(), 'altText' => $item->getAltText(), 'position' => $item->getSortOrder(), 'createdAt' => $item->getCreatedAt()->format(DATE_ATOM)];
    }
    private function copyMedia(Product $parent, Product $variant, EntityManagerInterface $entityManager): void
    {
        if ($entityManager->getRepository(ProductMedia::class)->count(['product' => $variant]) > 0) {
            return;
        }
        foreach ($entityManager->getRepository(ProductMedia::class)->findBy(['product' => $parent], ['sortOrder' => 'ASC']) as $item) {
            $entityManager->persist(new ProductMedia(
                $variant->getTenant(),
                $variant,
                $item->getMedia(),
                $item->getSortOrder(),
            ));
        }
    }
    private function mediaFileName(string $name, string $extension): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\\\\\/]+/', '-', basename($name)) ?? '');
        if ($name === '') {
            $name = 'image.'.$extension;
        } if (!str_contains($name, '.')) {
            $name .= '.'.$extension;
        }

return mb_substr($name, 0, 255);
    }
    /** @param array<string, true> $usedNames */
    private function uniqueMediaFileName(string $name, array $usedNames): string
    {
        if (!isset($usedNames[mb_strtolower($name)])) {
            return $name;
        } $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $suffix = 2;
        do {
            $candidate = mb_substr($base, 0, max(1, 250 - mb_strlen((string) $suffix) - mb_strlen($extension))).' ('.$suffix++.')'.($extension !== '' ? '.'.$extension : '');
        } while (isset($usedNames[mb_strtolower($candidate)]));

        return $candidate;
    }
    private function pricePayload(ProductPrice $item): array
    {
        $entry = $this->firstPrice($item->getPrice()) ?? [];
        $list = is_array($entry['listPrice'] ?? null) ? $entry['listPrice'] : [];
        $cheapest = is_array($entry['regulationPrice'] ?? null) ? $entry['regulationPrice'] : [];

        return ['id' => $item->getId()->toRfc4122(), 'currency' => $item->getCurrency()->getCode(), 'currencySymbol' => $item->getCurrency()->getSymbol(), 'priceType' => $item->getPriceType(), 'price' => $item->getPrice(), 'netAmount' => $this->cents($entry['net'] ?? null), 'grossAmount' => $this->cents($entry['gross'] ?? null), 'taxRate' => $item->getTaxRate(), 'quantityStart' => $this->displayDecimal($item->getQuantityStart()), 'quantityEnd' => $this->displayDecimal($item->getQuantityEnd()), 'pricingContext' => $item->getPricingContext(), 'listNetAmount' => $this->cents($list['net'] ?? null), 'listGrossAmount' => $this->cents($list['gross'] ?? null), 'cheapestNetAmount' => $this->cents($cheapest['net'] ?? null), 'cheapestGrossAmount' => $this->cents($cheapest['gross'] ?? null), 'linked' => (bool) ($entry['linked'] ?? true), 'listLinked' => (bool) ($list['linked'] ?? true), 'cheapestLinked' => (bool) ($cheapest['linked'] ?? true), 'validFrom' => $item->getValidFrom()?->format(DATE_ATOM), 'validUntil' => $item->getValidUntil()?->format(DATE_ATOM)];
    }
    private function translationPayload(ProductTranslation $item): array
    {
        $paths = $this->seoUrls->canonicalPaths(
            $item->getProduct()->getTenant(),
            SeoUrlService::ENTITY_PRODUCT,
            $item->getProduct()->getId(),
        );

        return [
            'locale' => $item->getLocale()->getCode(),
            'name' => $item->getName(),
            'shortDescription' => $item->getShortDescription(),
            'description' => $item->getDescription(),
            'metaTitle' => $item->getMetaTitle(),
            'metaDescription' => $item->getMetaDescription(),
            'metaKeywords' => $item->getMetaKeywords(),
            'seoUrl' => $paths[$item->getLocale()->getCode()] ?? null,
            'customFields' => $item->getCustomFields(),
        ];
    }
    private static function localeLabel(string $code): string
    {
        return \Locale::getDisplayRegion(str_replace('-', '_', $code), strtolower(explode('-', $code)[0])).' - '.$code;
    }
    /** @param list<array{string, list<string>}> $sets @return list<array<string, string>> */
    private function combinations(array $sets, int $offset = 0, array $current = []): array
    {
        if ($offset === count($sets)) {
            return [$current];
        }
        [$name, $values] = $sets[$offset];
        $result = [];
        foreach ($values as $value) {
            foreach ($this->combinations($sets, $offset + 1, [...$current, $name => $value]) as $combination) {
                $result[] = $combination;
            }
        }

        return $result;
    }
}
