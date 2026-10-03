<?php

namespace App\Controller\Api;

use App\Entity\Locale;
use App\Entity\Media;
use App\Entity\ProductVariantOptionGroup;
use App\Entity\ProductVariantOptionGroupProperty;
use App\Entity\ProductVariantOptionValue;
use App\Entity\Property;
use App\Entity\PropertyGroup;
use App\Entity\PropertyGroupTranslation;
use App\Entity\PropertyTranslation;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Service\PropertyValueReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/property-groups')]
final class PropertyGroupController extends AbstractController
{
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
                    'code' => $locale->getCode(),
                    'label' => \Locale::getDisplayRegion(
                        str_replace('-', '_', $locale->getCode()),
                        strtolower(explode('-', $locale->getCode())[0]),
                    ).' - '.$locale->getCode(),
                ],
                $locales,
            ),
        ]);
    }
    #[Route('', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $limit = $request->query->getInt('limit');
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim((string) $request->query->get('search', ''));
        $sort = (string) $request->query->get('sort', 'position');
        $direction = strtoupper((string) $request->query->get('direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $sortField = match ($sort) {
            'name' => 'propertyGroup.name',
            'code' => 'propertyGroup.code',
            'displayType' => 'propertyGroup.displayType',
            'position' => 'propertyGroup.position',
            default => 'propertyGroup.position',
        };
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('propertyGroup')
            ->from(PropertyGroup::class, 'propertyGroup')
            ->where('propertyGroup.tenant = :tenant')
            ->setParameter('tenant', $tenant);
        if ($search !== '') {
            $queryBuilder
                ->andWhere('LOWER(propertyGroup.name) LIKE :search OR LOWER(propertyGroup.code) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }
        $queryBuilder->orderBy($sortField, $direction);

        $pageSize = $limit > 0 ? min($limit, 100) : null;
        if ($pageSize !== null) {
            $queryBuilder
                ->setMaxResults($pageSize)
                ->setFirstResult(($page - 1) * $pageSize);
        }
        $groups = $queryBuilder->getQuery()->getResult();
        $propertyCounts = $this->propertyCounts($groups, $entityManager);
        $response = [
            'propertyGroups' => array_map(
                fn (PropertyGroup $group) => $this->payload(
                    $group,
                    $entityManager,
                    false,
                    $propertyCounts[$group->getId()->toRfc4122()] ?? 0,
                ),
                $groups,
            ),
        ];
        if ($pageSize !== null) {
            $totalQuery = $entityManager->createQueryBuilder()
                ->select('COUNT(propertyGroup.id)')
                ->from(PropertyGroup::class, 'propertyGroup')
                ->where('propertyGroup.tenant = :tenant')
                ->setParameter('tenant', $tenant);
            if ($search !== '') {
                $totalQuery
                    ->andWhere('LOWER(propertyGroup.name) LIKE :search OR LOWER(propertyGroup.code) LIKE :search')
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

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $group = $this->group($id, $entityManager);

        return $this->json(['propertyGroup' => $this->payload($group, $entityManager)]);
    }

    #[Route('/{id}/translations/{localeCode}', methods: ['GET', 'PUT'])]
    public function translations(string $id, string $localeCode, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $group = $this->group($id, $entityManager, $request->isMethod('PUT'));
        $locale = $entityManager->getRepository(Locale::class)->findOneBy(['code' => $localeCode, 'active' => true]);
        if (!$locale instanceof Locale) {
            throw $this->createNotFoundException();
        }
        if ($request->isMethod('GET')) {
            $groupTranslation = $entityManager->getRepository(PropertyGroupTranslation::class)->findOneBy(['propertyGroup' => $group, 'locale' => $locale]);
            $translations = $entityManager->getRepository(PropertyTranslation::class)->findBy(['locale' => $locale]);
            $byProperty = [];
            foreach ($translations as $translation) {
                $byProperty[$translation->getProperty()->getId()->toRfc4122()] = $translation->getName();
            }
            $properties = $entityManager->getRepository(Property::class)->findBy(['propertyGroup' => $group], ['position' => 'ASC']);

            return $this->json(['translation' => ['name' => $groupTranslation?->getName(), 'properties' => array_map(static fn (Property $property) => ['id' => $property->getId()->toRfc4122(), 'name' => $byProperty[$property->getId()->toRfc4122()] ?? null], $properties)]]);
        }
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->invalid($translator);
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $this->invalid($translator, ['name'], true);
        }
        $groupTranslation = $entityManager->getRepository(PropertyGroupTranslation::class)->findOneBy(['propertyGroup' => $group, 'locale' => $locale]);
        $groupTranslation ??= new PropertyGroupTranslation($group, $locale, $name);
        $groupTranslation->update($name);
        $entityManager->persist($groupTranslation);
        $properties = [];
        foreach ($entityManager->getRepository(Property::class)->findBy(['propertyGroup' => $group]) as $property) {
            $properties[$property->getId()->toRfc4122()] = $property;
        }
        foreach ((array) ($data['properties'] ?? []) as $item) {
            if (!is_array($item) || !isset($properties[$item['id'] ?? ''])) {
                return $this->invalid($translator);
            } $propertyName = trim((string) ($item['name'] ?? ''));
            if ($propertyName === '') {
                return $this->invalid($translator);
            } $property = $properties[$item['id']];
            $translation = $entityManager->getRepository(PropertyTranslation::class)->findOneBy(['property' => $property, 'locale' => $locale]) ?? new PropertyTranslation($property, $locale, $propertyName);
            $translation->update($propertyName);
            $entityManager->persist($translation);
        }
        $entityManager->flush();

        return $this->json(['message' => $this->message($translator, 'product.updated')]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->invalid($translator);
        }
        $name = trim((string) ($data['name'] ?? ''));
        $code = $this->code(trim((string) ($data['code'] ?? '')) ?: $name);
        $displayType = (string) ($data['displayType'] ?? 'text');
        $sorting = (string) ($data['sorting'] ?? 'custom');
        if ($name === '') {
            return $this->invalid($translator, ['name'], true);
        }
        if (
            $code === ''
            || !in_array($displayType, ['text', 'color', 'image', 'dropdown'], true)
            || !in_array($sorting, ['alphanumeric', 'custom'], true)
        ) {
            return $this->invalid($translator);
        }
        if ($entityManager->getRepository(PropertyGroup::class)->findOneBy(['tenant' => $tenant, 'code' => $code]) instanceof PropertyGroup) {
            return $this->json(['message' => $this->message($translator, 'property.group_code_used')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $group = new PropertyGroup($tenant, $name, $code, $displayType, (bool) ($data['isFilterable'] ?? true), (bool) ($data['displayOnProductDetail'] ?? true), $sorting, max(0, (int) ($data['position'] ?? $entityManager->getRepository(PropertyGroup::class)->count(['tenant' => $tenant]))));
        $entityManager->persist($group);
        foreach ((array) ($data['properties'] ?? []) as $position => $property) {
            $propertyName = trim((string) (is_array($property) ? ($property['name'] ?? '') : $property));
            if ($propertyName === '') {
                continue;
            }
            $propertyCode = $this->code(is_array($property) ? (trim((string) ($property['code'] ?? '')) ?: $propertyName) : $propertyName);
            $colorHex = is_array($property) ? $this->color($property['colorHex'] ?? null) : null;
            if ($propertyCode === '' || $entityManager->getRepository(Property::class)->findOneBy(['propertyGroup' => $group, 'code' => $propertyCode]) instanceof Property) {
                return $this->invalid($translator);
            }
            $entityManager->persist(new Property($tenant, $group, $propertyName, $propertyCode, $colorHex, $position));
        }
        $entityManager->flush();

        return $this->json(['propertyGroup' => $this->payload($group, $entityManager)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $group = $this->group($id, $entityManager, true);
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->invalid($translator);
        }
        $name = trim((string) ($data['name'] ?? $group->getName()));
        $code = $this->code(trim((string) ($data['code'] ?? '')) ?: $group->getCode());
        $displayType = (string) ($data['displayType'] ?? $group->getDisplayType());
        $sorting = (string) ($data['sorting'] ?? $group->getSorting());
        if ($name === '') {
            return $this->invalid($translator, ['name'], true);
        }
        if (
            $code === ''
            || !in_array($displayType, ['text', 'color', 'image', 'dropdown'], true)
            || !in_array($sorting, ['alphanumeric', 'custom'], true)
        ) {
            return $this->invalid($translator);
        }
        $group->update($name, $code, $displayType, (bool) ($data['isFilterable'] ?? $group->isFilterable()), (bool) ($data['displayOnProductDetail'] ?? $group->isDisplayedOnProductDetail()), $sorting, max(0, (int) ($data['position'] ?? $group->getPosition())));
        $entityManager->flush();

        return $this->json(['propertyGroup' => $this->payload($group, $entityManager)]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $group = $this->group($id, $entityManager, true);
        $properties = $entityManager->getRepository(Property::class)->findBy(['propertyGroup' => $group]);
        $usedByVariants = $entityManager->getRepository(ProductVariantOptionGroup::class)->count(['propertyGroup' => $group]) > 0;
        foreach ($properties as $property) {
            $usedByVariants = $usedByVariants || $entityManager->getRepository(ProductVariantOptionGroupProperty::class)->count(['property' => $property]) > 0 || $entityManager->getRepository(ProductVariantOptionValue::class)->count(['property' => $property]) > 0;
        }
        if ($usedByVariants) {
            return $this->json(['message' => $this->message($translator, 'property.in_use')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $entityManager->remove($group);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/properties', methods: ['GET'])]
    public function values(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        PropertyValueReader $values,
    ): JsonResponse
    {
        $group = $this->group($id, $entityManager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 25)));
        $search = mb_strtolower(trim((string) $request->query->get('search', '')));
        $locale = $entityManager->getRepository(Locale::class)->findOneBy([
            'code' => (string) $request->query->get('locale', $group->getTenant()->getDefaultSnippetLocale()),
            'active' => true,
        ]);
        $query = $entityManager->createQueryBuilder()
            ->select('property')
            ->from(Property::class, 'property')
            ->where('property.propertyGroup = :group')
            ->andWhere('property.tenant = :tenant')
            ->setParameter('group', $group)
            ->setParameter('tenant', $group->getTenant())
            ->leftJoin(
                PropertyTranslation::class,
                'translation',
                'WITH',
                'translation.property = property AND translation.locale = :locale',
            )
            ->setParameter('locale', $locale);
        if ($search !== '') {
            $query
                ->andWhere('LOWER(COALESCE(translation.name, property.name)) LIKE :search OR LOWER(property.code) LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }
        $count = clone $query;
        $total = (int) $count->select('COUNT(property.id)')->getQuery()->getSingleScalarResult();
        $records = $query
            ->addSelect('COALESCE(translation.name, property.name) AS HIDDEN localizedName')
            ->orderBy($group->getSorting() === 'alphanumeric' ? 'localizedName' : 'property.position', 'ASC')
            ->addOrderBy('property.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'properties' => $values->payloads($records, $entityManager),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'hasMore' => $page * $limit < $total,
            ],
        ]);
    }

    #[Route('/{id}/properties', methods: ['POST'])]
    public function createProperty(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $group = $this->group($id, $entityManager, true);
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->invalid($translator);
        }
        $name = trim((string) ($data['name'] ?? ''));
        $code = $this->code(trim((string) ($data['code'] ?? '')) ?: $name);
        if ($name === '') {
            return $this->invalid($translator, ['name'], true);
        }
        if (
            $code === ''
            || $entityManager->getRepository(Property::class)->findOneBy([
                'propertyGroup' => $group,
                'code' => $code,
            ]) instanceof Property
        ) {
            return $this->invalid($translator);
        }
        $property = new Property($group->getTenant(), $group, $name, $code, $this->color($data['colorHex'] ?? null), max(0, (int) ($data['position'] ?? $entityManager->getRepository(Property::class)->count(['propertyGroup' => $group]))));
        if (($data['mediaId'] ?? null) !== null && is_string($data['mediaId']) && Uuid::isValid($data['mediaId'])) {
            $media = $entityManager->getRepository(Media::class)->findOneBy(['id' => Uuid::fromString($data['mediaId']), 'tenant' => $group->getTenant()]);
            if ($media instanceof Media) {
                $property->update($name, $code, $this->color($data['colorHex'] ?? null), $property->getPosition(), $media);
            }
        }
        $entityManager->persist($property);
        $entityManager->flush();

        return $this->json(['property' => $this->propertyPayload($property)], Response::HTTP_CREATED);
    }

    #[Route('/{id}/properties/{propertyId}', methods: ['PATCH'])]
    public function updateProperty(string $id, string $propertyId, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $group = $this->group($id, $entityManager, true);
        if (!Uuid::isValid($propertyId)) {
            throw $this->createNotFoundException();
        }
        $property = $entityManager->getRepository(Property::class)->findOneBy(['id' => Uuid::fromString($propertyId), 'propertyGroup' => $group]);
        if (!$property instanceof Property) {
            throw $this->createNotFoundException();
        }
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->invalid($translator);
        }
        $name = trim((string) ($data['name'] ?? $property->getName()));
        $code = $this->code(trim((string) ($data['code'] ?? '')) ?: $property->getCode());
        $duplicate = $entityManager->getRepository(Property::class)->findOneBy(['propertyGroup' => $group, 'code' => $code]);
        if ($name === '') {
            return $this->invalid($translator, ['name'], true);
        }
        if (
            $code === ''
            || ($duplicate instanceof Property
                && $duplicate->getId()->toRfc4122() !== $property->getId()->toRfc4122())
        ) {
            return $this->invalid($translator);
        }
        $media = null;
        if (($data['mediaId'] ?? null) !== null && $data['mediaId'] !== '') {
            if (!is_string($data['mediaId']) || !Uuid::isValid($data['mediaId'])) {
                return $this->invalid($translator);
            } $media = $entityManager->getRepository(Media::class)->findOneBy(['id' => Uuid::fromString($data['mediaId']), 'tenant' => $group->getTenant()]);
            if (!$media instanceof Media) {
                return $this->invalid($translator);
            }
        }
        $property->update($name, $code, $this->color($data['colorHex'] ?? $property->getColorHex()), max(0, (int) ($data['position'] ?? $property->getPosition())), $media ?? $property->getMedia());
        $entityManager->flush();

        return $this->json(['property' => $this->propertyPayload($property)]);
    }

    #[Route('/{id}/properties/{propertyId}', methods: ['DELETE'])]
    public function deleteProperty(string $id, string $propertyId, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $group = $this->group($id, $entityManager, true);
        if (!Uuid::isValid($propertyId)) {
            throw $this->createNotFoundException();
        }
        $property = $entityManager->getRepository(Property::class)->findOneBy(['id' => Uuid::fromString($propertyId), 'propertyGroup' => $group]);
        if (!$property instanceof Property) {
            throw $this->createNotFoundException();
        }

        $usedForVariants = $entityManager->getRepository(ProductVariantOptionGroupProperty::class)->count(['property' => $property]) > 0
            || $entityManager->getRepository(ProductVariantOptionValue::class)->count(['property' => $property]) > 0;
        if ($usedForVariants) {
            return $this->json(['message' => $this->message($translator, 'property.in_use')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entityManager->remove($property);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function group(string $id, EntityManagerInterface $entityManager, bool $ownerRequired = false): PropertyGroup
    {
        $tenant = $this->tenant($entityManager, $ownerRequired);
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        } $group = $entityManager->getRepository(PropertyGroup::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant]);
        if (!$group instanceof PropertyGroup) {
            throw $this->createNotFoundException();
        }

return $group;
    }
    private function tenant(EntityManagerInterface $entityManager, bool $ownerRequired = false): Tenant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        } $membership = $entityManager->getRepository(TenantMembership::class)->forUser($user);
        if (!$membership instanceof TenantMembership || ($ownerRequired && $membership->getRole() !== 'owner')) {
            throw $this->createAccessDeniedException();
        }

return $membership->getTenant();
    }
    private function payload(
        PropertyGroup $group,
        EntityManagerInterface $entityManager,
        bool $includeProperties = true,
        ?int $propertyCount = null,
    ): array
    {
        $translations = [];
        foreach (
            $entityManager->getRepository(PropertyGroupTranslation::class)->findBy([
                'propertyGroup' => $group,
            ]) as $translation
        ) {
            $translations[$translation->getLocale()->getCode()] = [
                'name' => $translation->getName(),
            ];
        }
        $translations[$group->getTenant()->getDefaultSnippetLocale()] ??= [
            'name' => $group->getName(),
        ];

        return [
            'id' => $group->getId()->toRfc4122(),
            'name' => $group->getName(),
            'translations' => $translations,
            'code' => $group->getCode(),
            'displayType' => $group->getDisplayType(),
            'isFilterable' => $group->isFilterable(),
            'displayOnProductDetail' => $group->isDisplayedOnProductDetail(),
            'sorting' => $group->getSorting(),
            'position' => $group->getPosition(),
            'properties' => $includeProperties
                ? array_map(
                    $this->propertyPayload(...),
                    $entityManager->getRepository(Property::class)->findBy(
                        ['propertyGroup' => $group],
                        [$group->getSorting() === 'alphanumeric' ? 'name' : 'position' => 'ASC'],
                    ),
                )
                : [],
            'propertyCount' => $includeProperties
                ? null
                : $propertyCount,
        ];
    }

    /** @param list<PropertyGroup> $groups */
    private function propertyCounts(
        array $groups,
        EntityManagerInterface $entityManager,
    ): array {
        if ($groups === []) {
            return [];
        }

        $rows = $entityManager->createQueryBuilder()
            ->select('IDENTITY(property.propertyGroup) AS groupId, COUNT(property.id) AS propertyCount')
            ->from(Property::class, 'property')
            ->where('property.propertyGroup IN (:groups)')
            ->setParameter('groups', $groups)
            ->groupBy('property.propertyGroup')
            ->getQuery()
            ->getScalarResult();
        $counts = [];
        foreach ($rows as $row) {
            if (!is_string($row['groupId'] ?? null)) {
                continue;
            }

            $counts[$row['groupId']] = (int) ($row['propertyCount'] ?? 0);
        }

        return $counts;
    }
    private function propertyPayload(Property $property): array
    {
        return ['id' => $property->getId()->toRfc4122(), 'name' => $property->getName(), 'code' => $property->getCode(), 'colorHex' => $property->getColorHex(), 'position' => $property->getPosition(), 'mediaId' => $property->getMedia()?->getId()->toRfc4122()];
    }
    private function code(mixed $value): string
    {
        $code = strtolower(trim((string) $value));
        $code = preg_replace('/[^a-z0-9]+/u', '_', $code) ?? '';

        return trim($code, '_');
    }
    private function color(mixed $value): ?string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : null;
    }
    /** @param list<string> $fields */
    private function invalid(
        TranslatorInterface $translator,
        array $fields = [],
        bool $required = false,
    ): JsonResponse
    {
        if ($fields === []) {
            return $this->json([
                'message' => $this->message($translator, 'property.invalid'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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
    private function message(TranslatorInterface $translator, string $key): string
    {
        $user = $this->getUser();

        return $translator->trans($key, locale: $user instanceof User ? $user->getLocale() : 'bs');
    }
}
