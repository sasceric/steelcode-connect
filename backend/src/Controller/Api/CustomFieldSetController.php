<?php

namespace App\Controller\Api;

use App\Entity\CustomField;
use App\Entity\CustomFieldOption;
use App\Entity\CustomFieldSet;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/custom-field-sets')]
final class CustomFieldSetController extends AbstractController
{
    private const TYPES = ['text', 'editor', 'number', 'date', 'checkbox', 'switch', 'select', 'entity', 'media', 'color', 'price', 'json'];

    #[Route('', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $tenant = $this->tenant($em);

        return $this->json([
            'sets' => array_map(
                fn (CustomFieldSet $set) => $this->payload($set, $em),
                $em->getRepository(CustomFieldSet::class)->findBy(
                    ['tenant' => $tenant],
                    ['position' => 'ASC'],
                ),
            ),
            'unassignedFields' => array_map(
                fn (CustomField $field) => $this->fieldPayload($field, $em),
                $em->getRepository(CustomField::class)->findBy(
                    ['tenant' => $tenant, 'customFieldSet' => null],
                    ['position' => 'ASC'],
                ),
            ),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, TranslatorInterface $translator): JsonResponse
    {
        $tenant = $this->tenant($em, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $name = $this->technical($data['technicalName'] ?? '');
        $labels = $this->labels($data['labels'] ?? []);
        $relations = $this->relations($data['relations'] ?? ['product']);
        if ($name === '' || !$this->hasOnlyDefaultLabel($labels, $tenant) || $relations === []) {
            return $this->invalid(
                $translator,
                array_filter([
                    $name === '' ? 'technicalName' : null,
                    !$this->hasOnlyDefaultLabel($labels, $tenant) ? 'label' : null,
                    $relations === [] ? 'relations' : null,
                ]),
                true,
            );
        }
        if ($em->getRepository(CustomFieldSet::class)->findOneBy([
            'tenant' => $tenant,
            'technicalName' => $name,
        ])) {
            return $this->invalid($translator, ['technicalName']);
        }
        $set = new CustomFieldSet($tenant, $name, $labels, $relations, max(0, (int)($data['position'] ?? $em->getRepository(CustomFieldSet::class)->count(['tenant' => $tenant]))));
        $em->persist($set);
        $em->flush();

        return $this->json(['set' => $this->payload($set, $em)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, EntityManagerInterface $em): JsonResponse
    {
        $set = $this->set($id, $em);

        return $this->json(['set' => $this->payload($set, $em)]);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): JsonResponse
    {
        $set = $this->set($id, $em, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $name = $this->technical($data['technicalName'] ?? $set->getTechnicalName());
        $labels = $this->labels($data['labels'] ?? $set->getLabels());
        $relations = $this->relations($data['relations'] ?? $set->getRelations());
        $duplicate = $em->getRepository(CustomFieldSet::class)->findOneBy(['tenant' => $set->getTenant(),'technicalName' => $name]);
        if ($name === '' || $labels === [] || $relations === []) {
            return $this->invalid(
                $translator,
                array_filter([
                    $name === '' ? 'technicalName' : null,
                    $labels === [] ? 'label' : null,
                    $relations === [] ? 'relations' : null,
                ]),
                true,
            );
        }
        if (
            $duplicate instanceof CustomFieldSet
            && $duplicate->getId()->toRfc4122() !== $set->getId()->toRfc4122()
        ) {
            return $this->invalid($translator, ['technicalName']);
        }
        $set->update($name, $labels, $relations, max(0, (int)($data['position'] ?? $set->getPosition())));
        $em->flush();

        return $this->json(['set' => $this->payload($set, $em)]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, EntityManagerInterface $em): JsonResponse
    {
        $set = $this->set($id, $em, true);
        $em->remove($set);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/fields', methods: ['POST'])]
    public function createField(string $id, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): JsonResponse
    {
        $set = $this->set($id, $em, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $name = $this->technical($data['technicalName'] ?? '');
        $type = (string)($data['type'] ?? '');
        $labels = $this->labels($data['labels'] ?? []);
        $config = is_array($data['config'] ?? null) ? $data['config'] : [];
        if (
            $name === ''
            || !$this->hasOnlyDefaultLabel($labels, $set->getTenant())
            || !in_array($type, self::TYPES, true)
        ) {
            return $this->invalid(
                $translator,
                array_filter([
                    $name === '' ? 'technicalName' : null,
                    !$this->hasOnlyDefaultLabel($labels, $set->getTenant()) ? 'label' : null,
                    !in_array($type, self::TYPES, true) ? 'type' : null,
                ]),
                true,
            );
        }
        if ($em->getRepository(CustomField::class)->findOneBy([
            'customFieldSet' => $set,
            'technicalName' => $name,
        ])) {
            return $this->invalid($translator, ['technicalName']);
        }
        if ($type === 'select' && !$this->validOptions($data['options'] ?? [], $set->getTenant())) {
            return $this->invalid($translator);
        }
        $field = new CustomField(
            $set->getTenant(),
            $set,
            $name,
            $type,
            $labels,
            $config,
            max(
                0,
                (int) ($data['position'] ?? $em->getRepository(CustomField::class)->count([
                    'customFieldSet' => $set,
                ])),
            ),
        );
        $em->persist($field);
        foreach ($data['options'] ?? [] as $position => $option) {
            $em->persist(new CustomFieldOption($field, $this->technical($option['technicalValue']), $this->labels($option['labels'] ?? []), $position));
        }
        $em->flush();

        return $this->json(['field' => $this->fieldPayload($field, $em)], Response::HTTP_CREATED);
    }

    #[Route('/{id}/fields/{fieldId}', methods: ['PATCH'])]
    public function updateField(string $id, string $fieldId, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): JsonResponse
    {
        $set = $this->set($id, $em, true);
        if (!Uuid::isValid($fieldId)) {
            throw $this->createNotFoundException();
        } $field = $em->getRepository(CustomField::class)->findOneBy(['id' => Uuid::fromString($fieldId),'customFieldSet' => $set]);
        if (!$field instanceof CustomField) {
            throw $this->createNotFoundException();
        }
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        } $name = $this->technical($data['technicalName'] ?? $field->getTechnicalName());
        $type = (string)($data['type'] ?? $field->getType());
        $labels = $this->labels($data['labels'] ?? $field->getLabels());
        $config = is_array($data['config'] ?? null) ? $data['config'] : $field->getConfig();
        if ($name === '' || $labels === [] || !in_array($type, self::TYPES, true)) {
            return $this->invalid(
                $translator,
                array_filter([
                    $name === '' ? 'technicalName' : null,
                    $labels === [] ? 'label' : null,
                    !in_array($type, self::TYPES, true) ? 'type' : null,
                ]),
                true,
            );
        }
        if ($type === 'select' && !$this->validOptions($data['options'] ?? [])) {
            return $this->invalid($translator, ['options']);
        }
        $field->update($name, $type, $labels, $config, max(0, (int)($data['position'] ?? $field->getPosition())));
        foreach ($em->getRepository(CustomFieldOption::class)->findBy(['customField' => $field]) as $option) {
            $em->remove($option);
        } foreach ($data['options'] ?? [] as $position => $option) {
            $em->persist(new CustomFieldOption($field, $this->technical($option['technicalValue']), $this->labels($option['labels'] ?? []), $position));
        } $em->flush();

        return $this->json(['field' => $this->fieldPayload($field, $em)]);
    }

    #[Route('/{id}/fields', methods: ['DELETE'])]
    public function deleteFields(string $id, Request $request, EntityManagerInterface $em, TranslatorInterface $translator): JsonResponse
    {
        $set = $this->set($id, $em, true);
        $data = $this->data($request, $translator);
        if ($data instanceof JsonResponse) {
            return $data;
        }
        $ids = $data['ids'] ?? [];
        if (!is_array($ids) || $ids === []) {
            return $this->invalid($translator);
        }
        foreach ($ids as $id) {
            if (!is_string($id) || !Uuid::isValid($id)) {
                return $this->invalid($translator);
            }
            $field = $em->getRepository(CustomField::class)->findOneBy(['id' => Uuid::fromString($id), 'customFieldSet' => $set]);
            if (!$field instanceof CustomField) {
                return $this->invalid($translator);
            }
            $em->remove($field);
        }
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function payload(CustomFieldSet $set, EntityManagerInterface $em): array
    {
        return ['id' => $set->getId()->toRfc4122(),'technicalName' => $set->getTechnicalName(),'labels' => $set->getLabels(),'relations' => $set->getRelations(),'position' => $set->getPosition(),'fields' => array_map(fn (CustomField $field) => $this->fieldPayload($field, $em), $em->getRepository(CustomField::class)->findBy(['customFieldSet' => $set], ['position' => 'ASC']))];
    }
    private function fieldPayload(CustomField $field, EntityManagerInterface $em): array
    {
        return ['id' => $field->getId()->toRfc4122(),'technicalName' => $field->getTechnicalName(),'type' => $field->getType(),'labels' => $field->getLabels(),'config' => $field->getConfig(),'position' => $field->getPosition(),'options' => array_map(static fn (CustomFieldOption $o) => ['id' => $o->getId()->toRfc4122(),'technicalValue' => $o->getTechnicalValue(),'labels' => $o->getLabels(),'position' => $o->getPosition()], $em->getRepository(CustomFieldOption::class)->findBy(['customField' => $field], ['position' => 'ASC']))];
    }
    private function set(string $id, EntityManagerInterface $em, bool $owner = false): CustomFieldSet
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }$set = $em->getRepository(CustomFieldSet::class)->findOneBy(['id' => Uuid::fromString($id),'tenant' => $this->tenant($em, $owner)]);
        if (!$set instanceof CustomFieldSet) {
            throw $this->createNotFoundException();
        }

return $set;
    }
    private function tenant(EntityManagerInterface $em, bool $owner = false): Tenant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }$membership = $em->getRepository(TenantMembership::class)->forUser($user);
        if (!$membership instanceof TenantMembership || ($owner && $membership->getRole() !== 'owner')) {
            throw $this->createAccessDeniedException();
        }

return $membership->getTenant();
    }
    private function data(Request $request, TranslatorInterface $t): array|JsonResponse
    {
        try {
            return $request->toArray();
        } catch (\JsonException) {
            return $this->invalid($t);
        }
    }
    private function technical(mixed $value): string
    {
        $value = strtolower(trim((string)$value));

        return trim(preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '', '_');
    }
    private function labels(mixed $value): array
    {
        if (!is_array($value)) {
            return[];
        }$result = [];
        foreach ($value as $locale => $label) {
            if (is_string($locale) && is_string($label) && trim($label) !== '') {
                $result[$locale] = trim($label);
            }
        }

return $result;
    }
    /** @param array<string, string> $labels */
    private function hasOnlyDefaultLabel(array $labels, Tenant $tenant): bool
    {
        return count($labels) === 1
            && isset($labels[$tenant->getDefaultSnippetLocale()])
            && trim($labels[$tenant->getDefaultSnippetLocale()]) !== '';
    }
    private function relations(mixed $value): array
    {
        if (!is_array($value)) {
            return[];
        }

return array_values(array_intersect(['product','category','manufacturer','customer','order','property_group','property','media'], array_filter($value, 'is_string')));
    }
    private function validOptions(mixed $options, ?Tenant $tenant = null): bool
    {
        if (!is_array($options) || $options === []) {
            return false;
        }$seen = [];
        foreach ($options as $option) {
            $labels = is_array($option) ? $this->labels($option['labels'] ?? []) : [];
            if (
                !is_array($option)
                || ($value = $this->technical($option['technicalValue'] ?? '')) === ''
                || isset($seen[$value])
                || $labels === []
                || ($tenant instanceof Tenant && !$this->hasOnlyDefaultLabel($labels, $tenant))
            ) {
                return false;
            }$seen[$value] = true;
        }

return true;
    }
    /** @param list<string> $fields */
    private function invalid(
        TranslatorInterface $t,
        array $fields = [],
        bool $required = false,
    ): JsonResponse
    {
        $locale = $this->getUser() instanceof User ? $this->getUser()->getLocale() : 'bs';
        if ($fields === []) {
            return $this->json([
                'message' => $t->trans('custom_field.invalid', locale: $locale),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $labels = array_map(
            fn (string $field): string => $t->trans(
                'field.'.str_replace('technicalName', 'technical_name', $field),
                locale: $locale,
            ),
            $fields,
        );

        return $this->json([
            'message' => $t->trans(
                $required ? 'validation.required_fields' : 'validation.invalid_fields',
                ['%fields%' => implode(', ', $labels)],
                locale: $locale,
            ),
            'errors' => array_fill_keys(
                $fields,
                $t->trans(
                    $required ? 'validation.required_field' : 'validation.invalid_field',
                    locale: $locale,
                ),
            ),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
