<?php

namespace App\Controller\Api;

use App\Entity\Category;
use App\Entity\CategoryProduct;
use App\Entity\CategoryTranslation;
use App\Entity\Locale;
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

#[Route('/api/v1/categories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private TranslatorInterface $translator,
        private SeoUrlService $seoUrls,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $tenant = $this->tenant($em);
        $criteria = ['tenant' => $tenant];
        $search = trim((string) $request->query->get('search', ''));
        $requestedIds = array_filter(
            explode(',', (string) $request->query->get('ids', '')),
            static fn (string $id): bool => Uuid::isValid($id),
        );

        if ($requestedIds !== []) {
            $criteria['id'] = array_map(
                static fn (string $id): Uuid => Uuid::fromString($id),
                $requestedIds,
            );
        }

        if ($request->query->has('parentId') && $requestedIds === [] && $search === '') {
            $parent = $this->parentForCreate($request->query->get('parentId'), $tenant, $em);
            if ($parent === false) {
                return $this->invalid();
            }
            $criteria['parent'] = $parent;
        }

        if ($search !== '') {
            $categories = $em->createQuery(
                'SELECT DISTINCT category
                 FROM App\\Entity\\Category category
                 INNER JOIN App\\Entity\\CategoryTranslation translation WITH translation.category = category
                 WHERE category.tenant = :tenant
                   AND LOWER(translation.name) LIKE :search
                 ORDER BY category.position ASC',
            )
                ->setParameter('tenant', $tenant)
                ->setParameter('search', '%' . mb_strtolower($search) . '%')
                ->getResult();
        } else {
            $categories = $em->getRepository(Category::class)->findBy($criteria, ['position' => 'ASC']);
        }
        $parentIdsWithChildren = $this->parentIdsWithChildren($categories, $tenant, $em);

        return $this->json(['categories' => array_map(
            function (Category $category) use ($parentIdsWithChildren, $em): array {
                return [
                    ...$this->payload($category, $em),
                    'hasChildren' => isset($parentIdsWithChildren[$category->getId()->toRfc4122()]),
                ];
            },
            $categories,
        )]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $tenant = $this->tenant($em, true);
        $data = $this->data($request);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') return $this->invalid(['name'], true);

        $parent = $this->parentForCreate($data['parentId'] ?? null, $tenant, $em);
        if ($parent === false) return $this->invalid();
        $siblings = $em->getRepository(Category::class)->findBy(
            ['tenant' => $tenant, 'parent' => $parent],
            ['position' => 'ASC'],
        );
        $position = min(max(0, (int) ($data['position'] ?? count($siblings))), count($siblings));
        foreach ($siblings as $index => $sibling) {
            if ($index >= $position) $sibling->move($parent, $index + 1);
        }
        $category = new Category($tenant, $position);
        $category->move($parent, $position);
        $em->persist($category);
        $locale = $this->locale($tenant->getDefaultSnippetLocale(), $em);
        if (!$locale instanceof Locale) return $this->invalid();
        $translation = new CategoryTranslation($category, $locale, $name);
        $translation->update(
            $name,
            null,
            null,
            null,
            null,
            [],
        );
        $em->persist($translation);
        $this->seoUrls->syncCanonical(
            $tenant,
            SeoUrlService::ENTITY_CATEGORY,
            $category->getId(),
            $locale,
            $name,
        );
        $em->flush();

        return $this->json(['category' => $this->payload($category, $em)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, EntityManagerInterface $em): JsonResponse
    {
        return $this->json(['category' => $this->payload($this->category($id, $em), $em)]);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $category = $this->category($id, $em, true);
        $data = $this->data($request);
        $media = $this->media($data['mediaId'] ?? null, $category->getTenant(), $em);
        if ($media === false) return $this->invalid();
        $category->update(
            (bool) ($data['active'] ?? $category->isActive()),
            (bool) ($data['visible'] ?? $category->isVisible()),
            $media,
        );
        $em->flush();

        return $this->json(['category' => $this->payload($category, $em)]);
    }

    #[Route('/{id}/translations/{localeCode}', methods: ['PUT'])]
    public function translation(string $id, string $localeCode, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $category = $this->category($id, $em, true);
        $locale = $this->locale($localeCode, $em);
        $data = $this->data($request);
        $name = trim((string) ($data['name'] ?? ''));
        if (!$locale instanceof Locale) return $this->invalid();
        if ($name === '') return $this->invalid(['name'], true);
        $translation = $em->getRepository(CategoryTranslation::class)->findOneBy(['category' => $category, 'locale' => $locale]);
        if (!$translation instanceof CategoryTranslation) {
            $translation = new CategoryTranslation($category, $locale, $name);
            $em->persist($translation);
        }
        $translation->update(
            $name,
            $this->nullable($data['description'] ?? null),
            $this->nullable($data['metaTitle'] ?? null),
            $this->nullable($data['metaDescription'] ?? null),
            $this->nullable($data['metaKeywords'] ?? null),
            is_array($data['customFields'] ?? null)
                ? $data['customFields']
                : $translation->getCustomFields(),
        );
        $this->seoUrls->syncCanonical(
            $category->getTenant(),
            SeoUrlService::ENTITY_CATEGORY,
            $category->getId(),
            $locale,
            $name,
            $this->nullable($data['seoUrl'] ?? null),
        );
        $em->flush();

        return $this->json(['category' => $this->payload($category, $em)]);
    }

    #[Route('/{id}/move', methods: ['POST'])]
    public function move(string $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $category = $this->category($id, $em, true);
        $data = $this->data($request);
        $parent = $this->parent($data['parentId'] ?? null, $category, $em);
        if ($parent === false) return $this->invalid();
        $siblings = array_values(array_filter(
            $em->getRepository(Category::class)->findBy(
                ['tenant' => $category->getTenant(), 'parent' => $parent],
                ['position' => 'ASC'],
            ),
            static fn (Category $sibling) => $sibling->getId()->toRfc4122() !== $category->getId()->toRfc4122(),
        ));
        $position = array_key_exists('position', $data) && $data['position'] !== null
            ? max(0, (int) $data['position'])
            : count($siblings);
        array_splice($siblings, min($position, count($siblings)), 0, [$category]);
        foreach ($siblings as $index => $sibling) $sibling->move($parent, $index);
        $em->flush();

        return $this->json(['category' => $this->payload($category, $em)]);
    }

    #[Route('/{id}/products', methods: ['PUT'])]
    public function products(string $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $category = $this->category($id, $em, true);
        $ids = $this->data($request)['productIds'] ?? [];
        if (!is_array($ids)) return $this->invalid();
        $products = [];
        foreach (array_values($ids) as $productId) {
            if (!is_string($productId) || !Uuid::isValid($productId)) return $this->invalid();
            $product = $em->getRepository(Product::class)->findOneBy(['id' => Uuid::fromString($productId), 'tenant' => $category->getTenant()]);
            if (!$product instanceof Product) return $this->invalid();
            $products[] = $product;
        }
        $existingAssignments = $em->getRepository(CategoryProduct::class)->findBy([
            'category' => $category,
        ]);
        $existingByProductId = [];
        foreach ($existingAssignments as $assignment) {
            $existingByProductId[$assignment->getProduct()->getId()->toRfc4122()] = $assignment;
        }
        foreach ($existingByProductId as $productId => $assignment) {
            if (!in_array($productId, $ids, true)) {
                $em->remove($assignment);
            } else {
                $assignment->claimSource('manual');
            }
        }
        foreach ($products as $position => $product) {
            if (!isset($existingByProductId[$product->getId()->toRfc4122()])) {
                $em->persist(new CategoryProduct($category, $product, $position));
            }
        }
        $em->flush();

        return $this->json(['category' => $this->payload($category, $em)]);
    }

    #[Route('/{id}', methods: ['DELETE'], priority: -1)]
    public function delete(string $id, EntityManagerInterface $em): Response
    {
        $category = $this->category($id, $em, true);
        $this->seoUrls->removeForEntity(
            $category->getTenant(),
            SeoUrlService::ENTITY_CATEGORY,
            $category->getId(),
        );
        $em->remove($category);
        $em->flush();
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/bulk', methods: ['DELETE'])]
    public function bulkDelete(Request $request, EntityManagerInterface $em): Response|JsonResponse
    {
        $tenant = $this->tenant($em, true);
        $ids = $this->data($request)['ids'] ?? null;
        if (!is_array($ids) || $ids === []) {
            return $this->invalid();
        }

        $requestedIds = [];
        foreach ($ids as $id) {
            if (!is_string($id) || !Uuid::isValid($id)) {
                return $this->invalid();
            }
            $requestedIds[Uuid::fromString($id)->toRfc4122()] = true;
        }

        /** @var list<Category> $allCategories */
        $allCategories = $em->getRepository(Category::class)->findBy(['tenant' => $tenant]);
        $categoriesById = [];
        $childrenByParentId = [];
        foreach ($allCategories as $category) {
            $id = $category->getId()->toRfc4122();
            $categoriesById[$id] = $category;
            $parentId = $category->getParent()?->getId()->toRfc4122();
            if ($parentId !== null) {
                $childrenByParentId[$parentId][] = $id;
            }
        }

        foreach (array_keys($requestedIds) as $id) {
            if (!isset($categoriesById[$id])) {
                return $this->invalid();
            }
        }

        $categoryIdsToDelete = $requestedIds;
        $pendingIds = array_keys($requestedIds);
        while ($pendingIds !== []) {
            $parentId = array_pop($pendingIds);
            foreach ($childrenByParentId[$parentId] ?? [] as $childId) {
                if (isset($categoryIdsToDelete[$childId])) {
                    continue;
                }
                $categoryIdsToDelete[$childId] = true;
                $pendingIds[] = $childId;
            }
        }

        $categories = array_map(
            static fn (string $id): Category => $categoriesById[$id],
            array_keys($categoryIdsToDelete),
        );
        foreach ($categories as $category) {
            $this->seoUrls->removeForEntity(
                $tenant,
                SeoUrlService::ENTITY_CATEGORY,
                $category->getId(),
            );
            $em->remove($category);
        }
        $em->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * @param list<Category> $categories
     *
     * @return array<string, true>
     */
    private function parentIdsWithChildren(array $categories, Tenant $tenant, EntityManagerInterface $em): array
    {
        if ($categories === []) {
            return [];
        }

        $rows = $em->createQuery(
            'SELECT IDENTITY(child.parent) AS parentId
             FROM App\\Entity\\Category child
             WHERE child.tenant = :tenant AND child.parent IN (:parents)
             GROUP BY child.parent',
        )
            ->setParameter('tenant', $tenant)
            ->setParameter('parents', $categories)
            ->getScalarResult();

        $parentIds = [];
        foreach ($rows as $row) {
            if (is_string($row['parentId'] ?? null)) {
                $parentIds[$row['parentId']] = true;
            }
        }

        return $parentIds;
    }

    private function payload(Category $category, EntityManagerInterface $em): array
    {
        $seoPaths = $this->seoUrls->canonicalPaths(
            $category->getTenant(),
            SeoUrlService::ENTITY_CATEGORY,
            $category->getId(),
        );
        $translations = [];
        foreach ($em->getRepository(CategoryTranslation::class)->findBy(['category' => $category]) as $translation) {
            $localeCode = $translation->getLocale()->getCode();
            $translations[$localeCode] = [
                'name' => $translation->getName(),
                'description' => $translation->getDescription(),
                'metaTitle' => $translation->getMetaTitle(),
                'metaDescription' => $translation->getMetaDescription(),
                'metaKeywords' => $translation->getMetaKeywords(),
                'seoUrl' => $seoPaths[$localeCode] ?? null,
                'customFields' => $translation->getCustomFields(),
            ];
        }
        $defaultTranslation = $translations[$category->getTenant()->getDefaultSnippetLocale()] ?? reset($translations) ?: [];

        return [
            'id' => $category->getId()->toRfc4122(),
            'parentId' => $category->getParent()?->getId()->toRfc4122(),
            'mediaId' => $category->getMedia()?->getId()->toRfc4122(),
            'name' => $defaultTranslation['name'] ?? '',
            'seoUrl' => $defaultTranslation['seoUrl'] ?? '',
            'position' => $category->getPosition(),
            'active' => $category->isActive(),
            'visible' => $category->isVisible(),
            'translations' => $translations,
            'productIds' => array_map(
                fn (CategoryProduct $item) => $item->getProduct()->getId()->toRfc4122(),
                $em->getRepository(CategoryProduct::class)->findBy(['category' => $category], ['position' => 'ASC']),
            ),
        ];
    }

    private function category(string $id, EntityManagerInterface $em, bool $owner = false): Category
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $category = $em->getRepository(Category::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $this->tenant($em, $owner),
        ]);
        if (!$category instanceof Category) {
            throw $this->createNotFoundException();
        }

        return $category;
    }

    private function parent(mixed $id, Category $category, EntityManagerInterface $em): Category|false|null
    {
        if ($id === null || $id === '') {
            return null;
        }
        if (!is_string($id) || !Uuid::isValid($id) || $id === $category->getId()->toRfc4122()) {
            return false;
        }

        $parent = $em->getRepository(Category::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $category->getTenant(),
        ]);
        if (!$parent instanceof Category) {
            return false;
        }

        for ($node = $parent; $node !== null; $node = $node->getParent()) {
            if ($node->getId()->equals($category->getId())) {
                return false;
            }
        }

        return $parent;
    }

    private function parentForCreate(mixed $id, Tenant $tenant, EntityManagerInterface $em): Category|false|null
    {
        if ($id === null || $id === '') {
            return null;
        }
        if (!is_string($id) || !Uuid::isValid($id)) {
            return false;
        }

        $parent = $em->getRepository(Category::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);

        return $parent instanceof Category ? $parent : false;
    }

    private function locale(string $code, EntityManagerInterface $em): ?Locale
    {
        return $em->getRepository(Locale::class)->findOneBy([
            'code' => $code,
            'active' => true,
        ]);
    }

    private function media(mixed $id, Tenant $tenant, EntityManagerInterface $em): Media|false|null
    {
        if ($id === null || $id === '') {
            return null;
        }
        if (!is_string($id) || !Uuid::isValid($id)) {
            return false;
        }

        $media = $em->getRepository(Media::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);

        return $media instanceof Media ? $media : false;
    }

    private function data(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\JsonException) {
            return [];
        }
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
    /** @param list<string> $fields */
    private function invalid(array $fields = [], bool $required = false): JsonResponse
    {
        $locale = $this->getUser() instanceof User ? $this->getUser()->getLocale() : 'bs';
        if ($fields === []) {
            return $this->json([
                'message' => $this->translator->trans('category.invalid', locale: $locale),
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
    private function tenant(EntityManagerInterface $em, bool $owner = false): Tenant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $membership = $em->getRepository(TenantMembership::class)->forUser($user);
        if (!$membership instanceof TenantMembership || ($owner && $membership->getRole() !== 'owner')) {
            throw $this->createAccessDeniedException();
        }

        return $membership->getTenant();
    }
}
