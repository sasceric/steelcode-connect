<?php

namespace App\Controller\Api;

use App\Entity\Supplier;
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

#[Route('/api/v1/inventory/suppliers')]
final class SupplierController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(max(1, $request->query->getInt('limit', 25)), 100);
        $search = mb_strtolower(trim((string) $request->query->get('search', '')));
        $activeOnly = $request->query->getBoolean('active', false);
        $sort = (string) $request->query->get('sort', 'name');
        $direction = strtoupper((string) $request->query->get('direction', 'ASC')) === 'DESC'
            ? 'DESC'
            : 'ASC';
        $sortField = match ($sort) {
            'code' => 'supplier.code',
            'email' => 'supplier.email',
            'active' => 'supplier.active',
            default => 'supplier.name',
        };
        $query = $entityManager->createQueryBuilder()
            ->select('supplier')
            ->from(Supplier::class, 'supplier')
            ->where('supplier.tenant = :tenant')
            ->setParameter('tenant', $tenant);
        $totalQuery = $entityManager->createQueryBuilder()
            ->select('COUNT(supplier.id)')
            ->from(Supplier::class, 'supplier')
            ->where('supplier.tenant = :tenant')
            ->setParameter('tenant', $tenant);
        if ($activeOnly) {
            $query->andWhere('supplier.active = true');
            $totalQuery->andWhere('supplier.active = true');
        }
        if ($search !== '') {
            $condition = 'LOWER(supplier.name) LIKE :search OR LOWER(supplier.code) LIKE :search OR LOWER(supplier.email) LIKE :search';
            $query->andWhere($condition)->setParameter('search', '%'.$search.'%');
            $totalQuery->andWhere($condition)->setParameter('search', '%'.$search.'%');
        }
        $suppliers = $query
            ->orderBy($sortField, $direction)
            ->addOrderBy('supplier.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        $total = (int) $totalQuery->getQuery()->getSingleScalarResult();

        return $this->json([
            'suppliers' => array_map($this->payload(...), $suppliers),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'hasMore' => $page * $limit < $total,
            ],
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        $data = $request->toArray();
        $name = trim((string) ($data['name'] ?? ''));
        $code = $this->code((string) ($data['code'] ?? $name));
        if ($name === '' || $code === '') {
            return $this->json(['message' => 'Name and code are required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($entityManager->getRepository(Supplier::class)->findOneBy(['tenant' => $tenant, 'code' => $code]) instanceof Supplier) {
            return $this->json(['message' => 'Supplier code already exists.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $supplier = new Supplier($tenant, $code, $name);
        $supplier->update(
            $name,
            $code,
            $this->nullable($data['email'] ?? null),
            $this->nullable($data['phone'] ?? null),
            true,
        );
        $supplier->updateDetails(
            $this->nullable($data['contactName'] ?? null),
            $this->nullable($data['street'] ?? null),
            $this->nullable($data['postalCode'] ?? null),
            $this->nullable($data['city'] ?? null),
            $this->nullable($data['country'] ?? null),
        );
        $entityManager->persist($supplier);
        $entityManager->flush();

        return $this->json(['supplier' => $this->payload($supplier)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $supplier = $entityManager->getRepository(Supplier::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);
        if (!$supplier instanceof Supplier) {
            throw $this->createNotFoundException();
        }
        $data = $request->toArray();
        $name = trim((string) ($data['name'] ?? $supplier->getName()));
        $code = $this->code((string) ($data['code'] ?? $supplier->getCode()));
        if ($name === '' || $code === '') {
            return $this->json(['message' => 'Name and code are required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $duplicate = $entityManager->getRepository(Supplier::class)->findOneBy([
            'tenant' => $tenant,
            'code' => $code,
        ]);
        if ($duplicate instanceof Supplier && $duplicate->getId()->toRfc4122() !== $id) {
            return $this->json(['message' => 'Supplier code already exists.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $supplier->update(
            $name,
            $code,
            array_key_exists('email', $data) ? $this->nullable($data['email']) : $supplier->getEmail(),
            array_key_exists('phone', $data) ? $this->nullable($data['phone']) : $supplier->getPhone(),
            (bool) ($data['active'] ?? $supplier->isActive()),
        );
        $supplier->updateDetails(
            array_key_exists('contactName', $data) ? $this->nullable($data['contactName']) : $supplier->getContactName(),
            array_key_exists('street', $data) ? $this->nullable($data['street']) : $supplier->getStreet(),
            array_key_exists('postalCode', $data) ? $this->nullable($data['postalCode']) : $supplier->getPostalCode(),
            array_key_exists('city', $data) ? $this->nullable($data['city']) : $supplier->getCity(),
            array_key_exists('country', $data) ? $this->nullable($data['country']) : $supplier->getCountry(),
        );
        $entityManager->flush();

        return $this->json(['supplier' => $this->payload($supplier)]);
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

    private function payload(Supplier $supplier): array
    {
        return [
            'id' => $supplier->getId()->toRfc4122(),
            'name' => $supplier->getName(),
            'code' => $supplier->getCode(),
            'email' => $supplier->getEmail(),
            'phone' => $supplier->getPhone(),
            'contactName' => $supplier->getContactName(),
            'street' => $supplier->getStreet(),
            'postalCode' => $supplier->getPostalCode(),
            'city' => $supplier->getCity(),
            'country' => $supplier->getCountry(),
            'active' => $supplier->isActive(),
        ];
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
