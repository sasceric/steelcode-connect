<?php

namespace App\Controller\Api;

use App\Entity\DeliveryTime;
use App\Entity\DeliveryTimeTranslation;
use App\Entity\Locale;
use App\Entity\SupplierOffer;
use App\Entity\Tax;
use App\Entity\TaxTranslation;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\Unit;
use App\Entity\UnitTranslation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/catalogue/references')]
final class ReferenceDataController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $type = (string) $request->query->get('type', '');
        if (in_array($type, ['taxes', 'units', 'delivery-times'], true)) {
            return $this->paginatedList($type, $request, $tenant, $entityManager);
        }

        return $this->json([
            'taxes' => array_map(
                fn (Tax $tax) => [
                    'id' => $tax->getId()->toRfc4122(),
                    'name' => $tax->getName(),
                    'labels' => $this->taxLabels($tax, $entityManager),
                    'rate' => $tax->getRate(),
                ],
                $entityManager->getRepository(Tax::class)->findBy(
                    ['tenant' => $tenant, 'active' => true],
                    ['rate' => 'ASC'],
                ),
            ),
            'units' => array_map(
                fn (Unit $unit) => [
                    'id' => $unit->getId()->toRfc4122(),
                    'code' => $unit->getCode(),
                    'symbol' => $unit->getSymbol(),
                    'labels' => $this->unitLabels($unit, $entityManager),
                ],
                $entityManager->getRepository(Unit::class)->findBy(
                    ['tenant' => $tenant, 'active' => true],
                    ['code' => 'ASC'],
                ),
            ),
            'deliveryTimes' => array_map(
                fn (DeliveryTime $deliveryTime) => [
                    'id' => $deliveryTime->getId()->toRfc4122(),
                    'labels' => $this->deliveryTimeLabels($deliveryTime, $entityManager),
                    'min' => $deliveryTime->getMin(),
                    'max' => $deliveryTime->getMax(),
                    'unit' => $deliveryTime->getUnit(),
                ],
                $entityManager->getRepository(DeliveryTime::class)->findBy(
                    ['tenant' => $tenant, 'active' => true],
                ),
            ),
        ]);
    }

    private function paginatedList(
        string $type,
        Request $request,
        Tenant $tenant,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $limit = $request->query->getInt('limit');
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim((string) $request->query->get('search', ''));
        [$entity, $key, $sortFields, $defaultSort, $payload] = match ($type) {
            'taxes' => [
                Tax::class,
                'taxes',
                ['name' => 'reference.name', 'rate' => 'reference.rate'],
                'name',
                fn (Tax $tax): array => [
                    'id' => $tax->getId()->toRfc4122(),
                    'name' => $tax->getName(),
                    'labels' => $this->taxLabels($tax, $entityManager),
                    'rate' => $tax->getRate(),
                ],
            ],
            'units' => [
                Unit::class,
                'units',
                ['code' => 'reference.code', 'symbol' => 'reference.symbol'],
                'code',
                fn (Unit $unit): array => [
                    'id' => $unit->getId()->toRfc4122(),
                    'code' => $unit->getCode(),
                    'symbol' => $unit->getSymbol(),
                    'labels' => $this->unitLabels($unit, $entityManager),
                ],
            ],
            default => [
                DeliveryTime::class,
                'deliveryTimes',
                ['min' => 'reference.min', 'max' => 'reference.max', 'unit' => 'reference.unit'],
                'min',
                fn (DeliveryTime $deliveryTime): array => [
                    'id' => $deliveryTime->getId()->toRfc4122(),
                    'labels' => $this->deliveryTimeLabels($deliveryTime, $entityManager),
                    'min' => $deliveryTime->getMin(),
                    'max' => $deliveryTime->getMax(),
                    'unit' => $deliveryTime->getUnit(),
                ],
            ],
        };
        $sort = (string) $request->query->get('sort', $defaultSort);
        $direction = strtoupper((string) $request->query->get('direction', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $legacyDeliveryTimeIds = $type === 'delivery-times' && $search !== ''
            ? $this->deliveryTimeIdsMatchingStoredLabels($tenant, $search, $entityManager)
            : [];
        $searchCondition = match ($type) {
            'taxes' => 'LOWER(reference.name) LIKE :search OR EXISTS ('
                .'SELECT 1 FROM '.TaxTranslation::class.' searchTranslation '
                .'WHERE searchTranslation.tax = reference AND LOWER(searchTranslation.name) LIKE :search'
                .')',
            'units' => 'LOWER(reference.code) LIKE :search OR LOWER(reference.symbol) LIKE :search OR EXISTS ('
                .'SELECT 1 FROM '.UnitTranslation::class.' searchTranslation '
                .'WHERE searchTranslation.unit = reference AND LOWER(searchTranslation.name) LIKE :search'
                .')',
            default => 'EXISTS (SELECT 1 FROM '.DeliveryTimeTranslation::class.' searchTranslation '
                .'WHERE searchTranslation.deliveryTime = reference AND LOWER(searchTranslation.name) LIKE :search)',
        };
        if ($legacyDeliveryTimeIds !== []) {
            $searchCondition .= ' OR reference.id IN (:legacyDeliveryTimeIds)';
        }
        $queryBuilder = $entityManager->createQueryBuilder()
            ->select('reference')
            ->from($entity, 'reference')
            ->where('reference.tenant = :tenant')
            ->andWhere('reference.active = true')
            ->setParameter('tenant', $tenant);
        if ($search !== '') {
            $queryBuilder
                ->andWhere($searchCondition)
                ->setParameter('search', '%'.mb_strtolower($search).'%');
            if ($legacyDeliveryTimeIds !== []) {
                $queryBuilder->setParameter('legacyDeliveryTimeIds', $legacyDeliveryTimeIds);
            }
        }
        $queryBuilder->orderBy($sortFields[$sort] ?? $sortFields[$defaultSort], $direction);
        $pageSize = $limit > 0 ? min($limit, 100) : null;
        if ($pageSize !== null) {
            $queryBuilder->setMaxResults($pageSize)->setFirstResult(($page - 1) * $pageSize);
        }
        $items = $queryBuilder->getQuery()->getResult();
        $response = [$key => array_map($payload, $items)];
        if ($pageSize !== null) {
            $totalQuery = $entityManager->createQueryBuilder()
                ->select('COUNT(reference.id)')
                ->from($entity, 'reference')
                ->where('reference.tenant = :tenant')
                ->andWhere('reference.active = true')
                ->setParameter('tenant', $tenant);
            if ($search !== '') {
                $totalQuery
                    ->andWhere($searchCondition)
                    ->setParameter('search', '%'.mb_strtolower($search).'%');
                if ($legacyDeliveryTimeIds !== []) {
                    $totalQuery->setParameter('legacyDeliveryTimeIds', $legacyDeliveryTimeIds);
                }
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

    /** @return list<Uuid> */
    private function deliveryTimeIdsMatchingStoredLabels(
        Tenant $tenant,
        string $search,
        EntityManagerInterface $entityManager,
    ): array {
        $ids = $entityManager->getConnection()->fetchFirstColumn(
            'SELECT id::text FROM delivery_times '
            .'WHERE tenant_id = :tenantId AND active = true AND LOWER(labels::text) LIKE :search',
            [
                'tenantId' => $tenant->getId()->toRfc4122(),
                'search' => '%'.mb_strtolower($search).'%',
            ],
        );

        return array_values(array_filter(
            array_map(
                static fn (mixed $id): ?Uuid => is_string($id) && Uuid::isValid($id)
                    ? Uuid::fromString($id)
                    : null,
                $ids,
            ),
            static fn (?Uuid $id): bool => $id instanceof Uuid,
        ));
    }

    #[Route('/taxes', methods: ['POST'])]
    public function createTax(
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
        $data = $this->data($request);
        $name = trim((string) ($data['name'] ?? ''));
        $labels = $this->labels($data['labels'] ?? []);
        if (!$this->hasOnlyDefaultLabel($labels, $tenant)) {
            return $this->validation($translator, ['name'], true);
        }

        $name = $labels[$tenant->getDefaultSnippetLocale()] ?? $name;
        $rate = $this->rate($data['rate'] ?? null);
        if ($name === '' || $rate === null) {
            return $this->validation(
                $translator,
                array_filter([
                    $name === '' ? 'name' : null,
                    $rate === null ? 'rate' : null,
                ]),
                true,
            );
        }

        if ($entityManager->getRepository(Tax::class)->findOneBy(['tenant' => $tenant, 'name' => $name])) {
            return $this->validation($translator, ['name']);
        }

        $tax = new Tax($tenant, $name, $rate);
        $entityManager->persist($tax);
        $this->saveTaxTranslations($tax, $labels, $entityManager);
        $entityManager->flush();

        return $this->json(['tax' => $this->taxPayload($tax)], Response::HTTP_CREATED);
    }

    #[Route('/units', methods: ['POST'])]
    public function createUnit(
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
        $data = $this->data($request);
        $code = strtolower(trim((string) ($data['code'] ?? '')));
        $symbol = trim((string) ($data['symbol'] ?? ''));
        $labels = $this->labels($data['labels'] ?? []);
        if ($code === '' || $symbol === '' || !$this->hasOnlyDefaultLabel($labels, $tenant)) {
            return $this->validation(
                $translator,
                array_filter([
                    $code === '' ? 'code' : null,
                    $symbol === '' ? 'symbol' : null,
                    !$this->hasOnlyDefaultLabel($labels, $tenant) ? 'label' : null,
                ]),
                true,
            );
        }

        if ($entityManager->getRepository(Unit::class)->findOneBy(['tenant' => $tenant, 'code' => $code])) {
            return $this->validation($translator, ['code']);
        }

        $unit = new Unit($tenant, $code, $symbol, $labels);
        $entityManager->persist($unit);
        $this->saveUnitTranslations($unit, $labels, $entityManager);
        $entityManager->flush();

        return $this->json(['unit' => $this->unitPayload($unit)], Response::HTTP_CREATED);
    }

    #[Route('/delivery-times', methods: ['POST'])]
    public function createDeliveryTime(
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
        $data = $this->data($request);
        $labels = $this->labels($data['labels'] ?? []);
        $min = filter_var($data['min'] ?? null, FILTER_VALIDATE_INT);
        $max = filter_var($data['max'] ?? null, FILTER_VALIDATE_INT);
        $unit = (string) ($data['unit'] ?? '');
        if (
            !$this->hasOnlyDefaultLabel($labels, $tenant)
            || $min === false
            || $max === false
            || $min < 0
            || $max < $min
            || !in_array($unit, ['second', 'minute', 'hour', 'day', 'week'], true)
        ) {
            return $this->validation(
                $translator,
                array_filter([
                    !$this->hasOnlyDefaultLabel($labels, $tenant) ? 'label' : null,
                    $min === false || $min < 0 ? 'min' : null,
                    $max === false || $max < $min ? 'max' : null,
                    !in_array($unit, ['second', 'minute', 'hour', 'day', 'week'], true) ? 'unit' : null,
                ]),
                true,
            );
        }

        $deliveryTime = new DeliveryTime(
            $tenant,
            $labels,
            $min,
            $max,
            $unit,
        );
        $entityManager->persist($deliveryTime);
        $this->saveDeliveryTimeTranslations($deliveryTime, $labels, $entityManager);
        $entityManager->flush();

        return $this->json(
            ['deliveryTime' => $this->deliveryTimePayload($deliveryTime)],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/taxes/{id}', methods: ['PATCH'])]
    public function updateTax(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        $tax = $this->tax($id, $this->tenant($entityManager, true), $entityManager);
        $data = $this->data($request);
        $name = trim((string) ($data['name'] ?? ''));
        $labels = $this->labels($data['labels'] ?? []);
        if ($labels !== []) {
            $name = reset($labels) ?: $name;
        }
        $rate = $this->rate($data['rate'] ?? null);
        if ($name === '' || $rate === null) {
            return $this->validation(
                $translator,
                array_filter([
                    $name === '' ? 'name' : null,
                    $rate === null ? 'rate' : null,
                ]),
                true,
            );
        }

        $tax->update($name, $rate, $tax->isActive());
        $this->saveTaxTranslations($tax, $labels, $entityManager);
        $entityManager->flush();

        return $this->json(['tax' => $this->taxPayload($tax)]);
    }

    #[Route('/taxes/{id}', methods: ['DELETE'])]
    public function deleteTax(
        string $id,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $entityManager->remove(
            $this->tax($id, $this->tenant($entityManager, true), $entityManager),
        );
        $entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/units/{id}', methods: ['PATCH'])]
    public function updateUnit(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
        $unit = $this->unit($id, $tenant, $entityManager);
        $data = $this->data($request);
        $code = strtolower(trim((string) ($data['code'] ?? '')));
        $symbol = trim((string) ($data['symbol'] ?? ''));
        $labels = $this->labels($data['labels'] ?? []);
        if ($code === '' || $symbol === '' || $labels === []) {
            return $this->validation(
                $translator,
                array_filter([
                    $code === '' ? 'code' : null,
                    $symbol === '' ? 'symbol' : null,
                    $labels === [] ? 'label' : null,
                ]),
                true,
            );
        }

        if ($code !== $unit->getCode() && $this->unitUsedByOffer($tenant, $unit->getCode(), $entityManager)) {
            return $this->json(['message' => 'This unit is used by supplier offers. Keep its code or update the offers first.'], Response::HTTP_CONFLICT);
        }

        $unit->update($code, $symbol, $labels, true);
        $this->saveUnitTranslations($unit, $labels, $entityManager);
        $entityManager->flush();

        return $this->json(['unit' => $this->unitPayload($unit)]);
    }

    #[Route('/units/{id}', methods: ['DELETE'])]
    public function deleteUnit(
        string $id,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
        $unit = $this->unit($id, $tenant, $entityManager);
        if ($this->unitUsedByOffer($tenant, $unit->getCode(), $entityManager)) {
            return $this->json(['message' => 'This unit is used by supplier offers and cannot be deleted.'], Response::HTTP_CONFLICT);
        }
        $entityManager->remove($unit);
        $entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/delivery-times/{id}', methods: ['PATCH'])]
    public function updateDeliveryTime(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        $deliveryTime = $this->deliveryTime(
            $id,
            $this->tenant($entityManager, true),
            $entityManager,
        );
        $data = $this->data($request);
        $labels = $this->labels($data['labels'] ?? []);
        $min = filter_var($data['min'] ?? null, FILTER_VALIDATE_INT);
        $max = filter_var($data['max'] ?? null, FILTER_VALIDATE_INT);
        $unit = (string) ($data['unit'] ?? '');
        if (
            $labels === []
            || $min === false
            || $max === false
            || $min < 0
            || $max < $min
            || !in_array($unit, ['second', 'minute', 'hour', 'day', 'week'], true)
        ) {
            return $this->validation(
                $translator,
                array_filter([
                    $labels === [] ? 'label' : null,
                    $min === false || $min < 0 ? 'min' : null,
                    $max === false || $max < $min ? 'max' : null,
                    !in_array($unit, ['second', 'minute', 'hour', 'day', 'week'], true) ? 'unit' : null,
                ]),
                true,
            );
        }

        $deliveryTime->update($labels, $min, $max, $unit, true);
        $this->saveDeliveryTimeTranslations($deliveryTime, $labels, $entityManager);
        $entityManager->flush();

        return $this->json(['deliveryTime' => $this->deliveryTimePayload($deliveryTime)]);
    }

    #[Route('/delivery-times/{id}', methods: ['DELETE'])]
    public function deleteDeliveryTime(
        string $id,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $entityManager->remove(
            $this->deliveryTime($id, $this->tenant($entityManager, true), $entityManager),
        );
        $entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed> */
    private function data(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\JsonException) {
            return [];
        }
    }

    private function rate(mixed $value): ?string
    {
        if (!is_numeric($value) || (float) $value < 0 || (float) $value > 100) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    /** @return array<string, string> */
    private function labels(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $labels = [];
        foreach ($value as $locale => $label) {
            if (is_string($locale) && is_string($label) && trim($label) !== '') {
                $labels[$locale] = trim($label);
            }
        }

        return $labels;
    }

    /** @param array<string, string> $labels */
    private function hasOnlyDefaultLabel(array $labels, Tenant $tenant): bool
    {
        return count($labels) === 1
            && isset($labels[$tenant->getDefaultSnippetLocale()])
            && trim($labels[$tenant->getDefaultSnippetLocale()]) !== '';
    }

    /** @param list<string> $fields */
    private function validation(
        TranslatorInterface $translator,
        array $fields,
        bool $required = false,
    ): JsonResponse
    {
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

    private function tax(
        string $id,
        Tenant $tenant,
        EntityManagerInterface $entityManager,
    ): Tax {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $tax = $entityManager->getRepository(Tax::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);
        if (!$tax instanceof Tax) {
            throw $this->createNotFoundException();
        }

        return $tax;
    }

    private function unit(
        string $id,
        Tenant $tenant,
        EntityManagerInterface $entityManager,
    ): Unit {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $unit = $entityManager->getRepository(Unit::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);
        if (!$unit instanceof Unit) {
            throw $this->createNotFoundException();
        }

        return $unit;
    }

    private function deliveryTime(
        string $id,
        Tenant $tenant,
        EntityManagerInterface $entityManager,
    ): DeliveryTime {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $deliveryTime = $entityManager->getRepository(DeliveryTime::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);
        if (!$deliveryTime instanceof DeliveryTime) {
            throw $this->createNotFoundException();
        }

        return $deliveryTime;
    }

    /** @return array<string, mixed> */
    private function taxPayload(Tax $tax): array
    {
        return [
            'id' => $tax->getId()->toRfc4122(),
            'name' => $tax->getName(),
            'rate' => $tax->getRate(),
        ];
    }

    /** @return array<string, string> */
    private function taxLabels(Tax $tax, EntityManagerInterface $entityManager): array
    {
        $labels = [];
        foreach ($entityManager->getRepository(TaxTranslation::class)->findBy(['tax' => $tax]) as $translation) {
            $labels[$translation->getLocale()->getCode()] = $translation->getName();
        }

        return $labels === [] ? ['bs-BA' => $tax->getName()] : $labels;
    }

    /** @return array<string, string> */
    private function unitLabels(Unit $unit, EntityManagerInterface $entityManager): array
    {
        $labels = [];
        foreach ($entityManager->getRepository(UnitTranslation::class)->findBy(['unit' => $unit]) as $translation) {
            $labels[$translation->getLocale()->getCode()] = $translation->getName();
        }

        return $labels === [] ? $unit->getLabels() : $labels;
    }

    /** @return array<string, string> */
    private function deliveryTimeLabels(
        DeliveryTime $deliveryTime,
        EntityManagerInterface $entityManager,
    ): array {
        $labels = [];
        foreach ($entityManager->getRepository(DeliveryTimeTranslation::class)->findBy([
            'deliveryTime' => $deliveryTime,
        ]) as $translation) {
            $labels[$translation->getLocale()->getCode()] = $translation->getName();
        }

        return $labels === [] ? $deliveryTime->getLabels() : $labels;
    }

    /** @param array<string, string> $labels */
    private function saveTaxTranslations(
        Tax $tax,
        array $labels,
        EntityManagerInterface $entityManager,
    ): void {
        foreach ($labels as $localeCode => $name) {
            $locale = $entityManager->getRepository(Locale::class)->findOneBy([
                'code' => $localeCode,
                'active' => true,
            ]);
            if (!$locale instanceof Locale) {
                continue;
            }

            $translation = $entityManager->getRepository(TaxTranslation::class)->findOneBy([
                'tax' => $tax,
                'locale' => $locale,
            ]);
            if ($translation instanceof TaxTranslation) {
                $translation->update($name);
                continue;
            }

            $entityManager->persist(new TaxTranslation($tax, $locale, $name));
        }
    }

    /** @param array<string, string> $labels */
    private function saveUnitTranslations(
        Unit $unit,
        array $labels,
        EntityManagerInterface $entityManager,
    ): void {
        foreach ($labels as $localeCode => $name) {
            $locale = $entityManager->getRepository(Locale::class)->findOneBy([
                'code' => $localeCode,
                'active' => true,
            ]);
            if (!$locale instanceof Locale) {
                continue;
            }

            $translation = $entityManager->getRepository(UnitTranslation::class)->findOneBy([
                'unit' => $unit,
                'locale' => $locale,
            ]);
            if ($translation instanceof UnitTranslation) {
                $translation->update($name);
                continue;
            }

            $entityManager->persist(new UnitTranslation($unit, $locale, $name));
        }
    }

    /** @param array<string, string> $labels */
    private function saveDeliveryTimeTranslations(
        DeliveryTime $deliveryTime,
        array $labels,
        EntityManagerInterface $entityManager,
    ): void {
        foreach ($labels as $localeCode => $name) {
            $locale = $entityManager->getRepository(Locale::class)->findOneBy([
                'code' => $localeCode,
                'active' => true,
            ]);
            if (!$locale instanceof Locale) {
                continue;
            }

            $translation = $entityManager->getRepository(DeliveryTimeTranslation::class)->findOneBy([
                'deliveryTime' => $deliveryTime,
                'locale' => $locale,
            ]);
            if ($translation instanceof DeliveryTimeTranslation) {
                $translation->update($name);
                continue;
            }

            $entityManager->persist(new DeliveryTimeTranslation($deliveryTime, $locale, $name));
        }
    }

    /** @return array<string, mixed> */
    private function unitPayload(Unit $unit): array
    {
        return [
            'id' => $unit->getId()->toRfc4122(),
            'code' => $unit->getCode(),
            'symbol' => $unit->getSymbol(),
            'labels' => $unit->getLabels(),
        ];
    }

    /** @return array<string, mixed> */
    private function deliveryTimePayload(DeliveryTime $deliveryTime): array
    {
        return [
            'id' => $deliveryTime->getId()->toRfc4122(),
            'labels' => $deliveryTime->getLabels(),
            'min' => $deliveryTime->getMin(),
            'max' => $deliveryTime->getMax(),
            'unit' => $deliveryTime->getUnit(),
        ];
    }

    private function tenant(
        EntityManagerInterface $entityManager,
        bool $ownerRequired = false,
    ): Tenant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $membership = $entityManager->getRepository(TenantMembership::class)->findOneBy([
            'user' => $user,
        ]);
        if (
            !$membership instanceof TenantMembership
            || ($ownerRequired && $membership->getRole() !== 'owner')
        ) {
            throw $this->createAccessDeniedException();
        }

        return $membership->getTenant();
    }

    private function unitUsedByOffer(Tenant $tenant, string $code, EntityManagerInterface $entityManager): bool
    {
        return (int) $entityManager->createQueryBuilder()
            ->select('COUNT(offer.id)')
            ->from(SupplierOffer::class, 'offer')
            ->where('offer.tenant = :tenant')
            ->andWhere('offer.purchaseUnit = :code')
            ->setParameter('tenant', $tenant)
            ->setParameter('code', $code)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }
}
