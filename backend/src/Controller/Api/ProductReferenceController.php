<?php

namespace App\Controller\Api;

use App\Entity\DeliveryTime;
use App\Entity\Manufacturer;
use App\Entity\Product;
use App\Entity\Tax;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\Unit;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/products/{id}/references')]
final class ProductReferenceController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function show(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $product = $this->product($id, $entityManager);

        return $this->json([
            'taxId' => $product->getTax()?->getId()->toRfc4122(),
            'manufacturerId' => $product->getManufacturer()?->getId()->toRfc4122(),
            'unitId' => $product->getUnit()?->getId()->toRfc4122(),
            'purchaseUnit' => $product->getPurchaseUnit(),
            'referenceUnit' => $product->getReferenceUnit(),
            'deliveryTimeId' => $product->getDeliveryTimeReference()?->getId()->toRfc4122(),
        ]);
    }

    #[Route('', methods: ['PATCH'])]
    public function update(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        $product = $this->product($id, $entityManager);
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->invalid();
        }

        $tax = $this->reference($data['taxId'] ?? null, Tax::class, $product->getTenant(), $entityManager);
        $manufacturer = $this->reference($data['manufacturerId'] ?? null, Manufacturer::class, $product->getTenant(), $entityManager);
        $unit = $this->reference($data['unitId'] ?? null, Unit::class, $product->getTenant(), $entityManager);
        $deliveryTime = $this->reference($data['deliveryTimeId'] ?? null, DeliveryTime::class, $product->getTenant(), $entityManager);
        $purchaseUnit = $this->decimal($data['purchaseUnit'] ?? null);
        $referenceUnit = $this->decimal($data['referenceUnit'] ?? null);
        if (!$tax instanceof Tax) {
            return $this->validation($translator, ['taxId' => 'product.field.tax_rate'], true);
        }
        if ($manufacturer === false || $unit === false || $deliveryTime === false || $purchaseUnit === false || $referenceUnit === false) {
            return $this->invalid($translator);
        }

        $product->updateReferences($tax, $unit, $purchaseUnit, $referenceUnit, $deliveryTime);
        $product->updateManufacturer($manufacturer instanceof Manufacturer ? $manufacturer : null);
        $entityManager->flush();

        return $this->json(['message' => 'Product references saved.']);
    }

    private function reference(
        mixed $id,
        string $class,
        Tenant $tenant,
        EntityManagerInterface $entityManager,
    ): object|false|null {
        if ($id === null || $id === '') {
            return null;
        }
        if (!is_string($id) || !Uuid::isValid($id)) {
            return false;
        }

        $reference = $entityManager->getRepository($class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);

        return $reference ?: false;
    }

    private function decimal(mixed $value): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value) || (float) $value <= 0) {
            return false;
        }

        return number_format((float) $value, 4, '.', '');
    }

    /** @param array<string, string> $fields */
    private function validation(
        TranslatorInterface $translator,
        array $fields,
        bool $required = false,
    ): JsonResponse {
        $user = $this->getUser();
        $locale = $user instanceof User ? $user->getLocale() : 'bs';
        $labels = [];
        $errors = [];

        foreach ($fields as $field => $labelKey) {
            $labels[] = $translator->trans($labelKey, locale: $locale);
            $errors[$field] = $translator->trans(
                $required
                    ? 'product.validation.required_field'
                    : 'product.validation.invalid_field',
                locale: $locale,
            );
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

    private function invalid(TranslatorInterface $translator): JsonResponse
    {
        $user = $this->getUser();

        return $this->json([
            'message' => $translator->trans(
                'product.invalid',
                locale: $user instanceof User ? $user->getLocale() : 'bs',
            ),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function product(string $id, EntityManagerInterface $entityManager): Product
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $product = $entityManager->getRepository(Product::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $this->tenant($entityManager),
        ]);
        if (!$product instanceof Product) {
            throw $this->createNotFoundException();
        }

        return $product;
    }

    private function tenant(EntityManagerInterface $entityManager): Tenant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $membership = $entityManager->getRepository(TenantMembership::class)->findOneBy(['user' => $user]);
        if (!$membership instanceof TenantMembership || $membership->getRole() !== 'owner') {
            throw $this->createAccessDeniedException();
        }

        return $membership->getTenant();
    }
}
