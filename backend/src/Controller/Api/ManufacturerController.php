<?php

namespace App\Controller\Api;

use App\Entity\Locale;
use App\Entity\Manufacturer;
use App\Entity\ManufacturerTranslation;
use App\Entity\Media;
use App\Entity\Product;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Service\SeoUrlService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/manufacturers')]
final class ManufacturerController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator,
        private SeoUrlService $seoUrls,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $limit = $request->query->getInt('limit');
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim((string) $request->query->get('search', ''));
        $sort = (string) $request->query->get('sort', 'name');
        $direction = strtoupper((string) $request->query->get('direction', 'ASC')) === 'DESC'
            ? 'DESC'
            : 'ASC';
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('manufacturer')
            ->from(Manufacturer::class, 'manufacturer')
            ->where('manufacturer.tenant = :tenant')
            ->setParameter('tenant', $tenant);

        if ($search !== '') {
            $queryBuilder
                ->andWhere(
                    'LOWER(COALESCE(manufacturer.website, \'\')) LIKE :search OR EXISTS ('
                    .'SELECT 1 FROM '.ManufacturerTranslation::class.' searchTranslation '
                    .'WHERE searchTranslation.manufacturer = manufacturer AND LOWER(searchTranslation.name) LIKE :search'
                    .')'
                )
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        $sortField = match ($sort) {
            'name' => 'sortName',
            'website' => 'manufacturer.website',
            default => 'sortName',
        };
        $sortLocale = $this->locale($tenant->getDefaultSnippetLocale(), $entityManager);
        if ($sortLocale instanceof Locale) {
            $queryBuilder
                ->addSelect('sortTranslation.name AS HIDDEN sortName')
                ->leftJoin(
                    ManufacturerTranslation::class,
                    'sortTranslation',
                    'WITH',
                    'sortTranslation.manufacturer = manufacturer AND sortTranslation.locale = :sortLocale',
                )
                ->setParameter('sortLocale', $sortLocale);
        } elseif ($sortField === 'sortName') {
            $sortField = 'manufacturer.id';
        }
        $queryBuilder->orderBy($sortField, $direction);

        $pageSize = $limit > 0 ? min($limit, 100) : null;
        if ($pageSize !== null) {
            $queryBuilder
                ->setMaxResults($pageSize)
                ->setFirstResult(($page - 1) * $pageSize);
        }

        $manufacturers = $queryBuilder->getQuery()->getResult();

        $response = [
            'manufacturers' => array_map(
                fn (Manufacturer $manufacturer) => $this->payload($manufacturer, $entityManager, false),
                $manufacturers,
            ),
        ];

        if ($pageSize !== null) {
            $total = $this->manufacturerListTotal($tenant, $search, $entityManager);
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
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $data = $this->data($request);
        $name = $this->nullable($data['name'] ?? null);
        $locale = $this->locale($tenant->getDefaultSnippetLocale(), $entityManager);
        if ($name === null || !$locale instanceof Locale) {
            return $this->invalid(['name'], true);
        }

        $manufacturer = new Manufacturer($tenant);
        $translation = new ManufacturerTranslation($manufacturer, $locale, $name);
        $translation->update($name, null, null, null, null, []);
        $entityManager->persist($manufacturer);
        $entityManager->persist($translation);
        $this->seoUrls->syncCanonical(
            $tenant,
            SeoUrlService::ENTITY_MANUFACTURER,
            $manufacturer->getId(),
            $locale,
            $name,
        );
        $entityManager->flush();

        return $this->json(
            ['manufacturer' => $this->payload($manufacturer, $entityManager)],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        return $this->json([
            'manufacturer' => $this->payload($this->manufacturer($id, $entityManager), $entityManager),
        ]);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $manufacturer = $this->manufacturer($id, $entityManager, true);
        $data = $this->data($request);
        $media = $this->media($data['mediaId'] ?? null, $manufacturer->getTenant(), $entityManager);
        if ($media === false) {
            return $this->invalid(['mediaId']);
        }
        $website = $this->nullable($data['website'] ?? $manufacturer->getWebsite());
        if ($website !== null && filter_var($website, FILTER_VALIDATE_URL) === false) {
            return $this->invalid(['website']);
        }
        $manufacturer->update($website, $media);
        $entityManager->flush();

        return $this->json(['manufacturer' => $this->payload($manufacturer, $entityManager)]);
    }

    #[Route('/{id}/translations/{localeCode}', methods: ['PUT'])]
    public function translation(string $id, string $localeCode, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $manufacturer = $this->manufacturer($id, $entityManager, true);
        $locale = $this->locale($localeCode, $entityManager);
        $data = $this->data($request);
        $name = $this->nullable($data['name'] ?? null);
        if (!$locale instanceof Locale) return $this->invalid();
        if ($name === null) return $this->invalid(['name'], true);
        $translation = $entityManager->getRepository(ManufacturerTranslation::class)->findOneBy([
            'manufacturer' => $manufacturer,
            'locale' => $locale,
        ]);
        if (!$translation instanceof ManufacturerTranslation) {
            $translation = new ManufacturerTranslation($manufacturer, $locale, $name);
            $entityManager->persist($translation);
        }
        $translation->update(
            $name,
            $this->nullable($data['description'] ?? null),
            $this->nullable($data['metaTitle'] ?? null),
            $this->nullable($data['metaDescription'] ?? null),
            $this->nullable($data['metaKeywords'] ?? null),
            is_array($data['customFields'] ?? null) ? $data['customFields'] : $translation->getCustomFields(),
        );
        $this->seoUrls->syncCanonical(
            $manufacturer->getTenant(),
            SeoUrlService::ENTITY_MANUFACTURER,
            $manufacturer->getId(),
            $locale,
            $name,
            $this->nullable($data['seoUrl'] ?? null),
        );
        $entityManager->flush();

        return $this->json(['manufacturer' => $this->payload($manufacturer, $entityManager)]);
    }

    #[Route('/{id}/products', methods: ['PUT'])]
    public function products(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse
    {
        $manufacturer = $this->manufacturer($id, $entityManager, true);
        $productIds = $this->data($request)['productIds'] ?? [];
        if (!is_array($productIds)) {
            return $this->invalid();
        }
        foreach ($productIds as $productId) {
            if (!is_string($productId) || !Uuid::isValid($productId)) {
                return $this->invalid();
            }
        }
        if (count($productIds) !== count(array_unique($productIds))) {
            return $this->invalid();
        }
        $repository = $entityManager->getRepository(Product::class);
        $products = $productIds === [] ? [] : $repository->findBy([
            'tenant' => $manufacturer->getTenant(),
            'id' => $productIds,
        ]);
        if (count($products) !== count($productIds)) {
            return $this->invalid();
        }
        $requested = array_fill_keys($productIds, true);
        foreach ($repository->findBy([
            'tenant' => $manufacturer->getTenant(),
            'manufacturer' => $manufacturer,
        ]) as $product) {
            $productId = $product->getId()->toRfc4122();
            if (!isset($requested[$productId])) {
                $product->updateManufacturer(null);
            }
        }
        foreach ($products as $product) {
            $product->updateManufacturer($manufacturer);
        }
        $entityManager->flush();

        return $this->json(['manufacturer' => $this->payload($manufacturer, $entityManager)]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, EntityManagerInterface $entityManager): Response
    {
        $manufacturer = $this->manufacturer($id, $entityManager, true);
        $this->seoUrls->removeForEntity(
            $manufacturer->getTenant(),
            SeoUrlService::ENTITY_MANUFACTURER,
            $manufacturer->getId(),
        );
        $entityManager->remove($manufacturer);
        $entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function payload(
        Manufacturer $manufacturer,
        EntityManagerInterface $entityManager,
        bool $includeProducts = true,
    ): array
    {
        $seoPaths = $this->seoUrls->canonicalPaths(
            $manufacturer->getTenant(),
            SeoUrlService::ENTITY_MANUFACTURER,
            $manufacturer->getId(),
        );
        $translations = [];
        foreach ($entityManager->getRepository(ManufacturerTranslation::class)->findBy(['manufacturer' => $manufacturer]) as $translation) {
            $localeCode = $translation->getLocale()->getCode();
            $translations[$translation->getLocale()->getCode()] = [
                'name' => $translation->getName(),
                'description' => $translation->getDescription(),
                'metaTitle' => $translation->getMetaTitle(),
                'metaDescription' => $translation->getMetaDescription(),
                'metaKeywords' => $translation->getMetaKeywords(),
                'seoUrl' => $seoPaths[$localeCode] ?? null,
                'customFields' => $translation->getCustomFields(),
            ];
        }
        $default = $translations[$manufacturer->getTenant()->getDefaultSnippetLocale()] ?? reset($translations) ?: [];
        $products = $includeProducts
            ? $entityManager->getRepository(Product::class)->findBy(['manufacturer' => $manufacturer])
            : [];

        return [
            'id' => $manufacturer->getId()->toRfc4122(),
            'name' => $default['name'] ?? '',
            'seoUrl' => $default['seoUrl'] ?? '',
            'website' => $manufacturer->getWebsite(),
            'mediaId' => $manufacturer->getMedia()?->getId()->toRfc4122(),
            'translations' => $translations,
            'productIds' => array_map(static fn (Product $product) => $product->getId()->toRfc4122(), $products),
        ];
    }

    private function manufacturerListTotal(
        Tenant $tenant,
        string $search,
        EntityManagerInterface $entityManager,
    ): int {
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('COUNT(manufacturer.id)')
            ->from(Manufacturer::class, 'manufacturer')
            ->where('manufacturer.tenant = :tenant')
            ->setParameter('tenant', $tenant);

        if ($search !== '') {
            $queryBuilder
                ->andWhere(
                    'LOWER(COALESCE(manufacturer.website, \'\')) LIKE :search OR EXISTS ('
                    .'SELECT 1 FROM '.ManufacturerTranslation::class.' searchTranslation '
                    .'WHERE searchTranslation.manufacturer = manufacturer AND LOWER(searchTranslation.name) LIKE :search'
                    .')'
                )
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    private function manufacturer(string $id, EntityManagerInterface $entityManager, bool $ownerRequired = false): Manufacturer
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $manufacturer = $entityManager->getRepository(Manufacturer::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $this->tenant($entityManager, $ownerRequired),
        ]);
        if (!$manufacturer instanceof Manufacturer) {
            throw $this->createNotFoundException();
        }

        return $manufacturer;
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

    private function locale(string $code, EntityManagerInterface $entityManager): ?Locale
    {
        return $entityManager->getRepository(Locale::class)->findOneBy(['code' => $code, 'active' => true]);
    }

    private function media(mixed $id, Tenant $tenant, EntityManagerInterface $entityManager): Media|false|null
    {
        if ($id === null || $id === '') {
            return null;
        }
        if (!is_string($id) || !Uuid::isValid($id)) {
            return false;
        }
        $media = $entityManager->getRepository(Media::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);

        return $media instanceof Media ? $media : false;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function data(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\JsonException) {
            return [];
        }
    }

    /** @param list<string> $fields */
    private function invalid(array $fields = [], bool $required = false): JsonResponse
    {
        $locale = $this->getUser() instanceof User ? $this->getUser()->getLocale() : 'bs';
        if ($fields === []) {
            return $this->json([
                'message' => $this->translator->trans('manufacturer.invalid', locale: $locale),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $labels = array_map(
            fn (string $field): string => $this->translator->trans('field.'.$field, locale: $locale),
            $fields,
        );

        return $this->json([
            'message' => $this->translator->trans(
                $required ? 'validation.required_fields' : 'validation.invalid_fields',
                ['%fields%' => implode(', ', $labels)],
                locale: $locale,
            ),
            'errors' => array_fill_keys(
                $fields,
                $this->translator->trans(
                    $required ? 'validation.required_field' : 'validation.invalid_field',
                    locale: $locale,
                ),
            ),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
