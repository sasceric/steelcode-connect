<?php

namespace App\Controller\Api;

use App\Entity\SalesOrder;
use App\Entity\SalesReturn;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Service\SalesReturnService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/sales/orders/{id}/returns')]
final class SalesReturnController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(
        string $id,
        EntityManagerInterface $entityManager,
        SalesReturnService $returns,
    ): JsonResponse {
        $order = $this->order($id, $entityManager);

        return $this->json([
            'options' => $returns->options($order, $entityManager),
            'returns' => array_map(
                $this->payload(...),
                $returns->records($order, $entityManager),
            ),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function receive(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        SalesReturnService $returns,
    ): JsonResponse {
        $order = $this->order($id, $entityManager, true);
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        try {
            $data = $request->toArray();
            if (!is_string($data['requestId'] ?? null)
                || !Uuid::isValid($data['requestId'])
                || !is_string($data['itemId'] ?? null)
                || !Uuid::isValid($data['itemId'])
                || !is_string($data['warehouseId'] ?? null)
                || !Uuid::isValid($data['warehouseId'])
                || !is_string($data['quantity'] ?? null)
                || !is_string($data['reason'] ?? null)
                || !is_string($data['condition'] ?? null)
                || !is_string($data['disposition'] ?? null)
                || (isset($data['note']) && !is_string($data['note']))) {
                throw new \InvalidArgumentException('Provide a return request, shipped item, warehouse, quantity, reason, condition, and disposition.');
            }

            $record = $returns->receive(
                $order,
                Uuid::fromString($data['requestId']),
                Uuid::fromString($data['itemId']),
                Uuid::fromString($data['warehouseId']),
                $data['quantity'],
                $data['reason'],
                $data['condition'],
                $data['disposition'],
                $data['note'] ?? null,
                $user,
                $entityManager,
            );
        } catch (\JsonException|\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['return' => $this->payload($record)]);
    }

    #[Route('/{returnId}/disposition', methods: ['POST'])]
    public function resolve(
        string $id,
        string $returnId,
        Request $request,
        EntityManagerInterface $entityManager,
        SalesReturnService $returns,
    ): JsonResponse {
        $order = $this->order($id, $entityManager, true);
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        try {
            $data = $request->toArray();
            if (!Uuid::isValid($returnId)
                || !is_string($data['disposition'] ?? null)
                || !is_string($data['condition'] ?? null)) {
                throw new \InvalidArgumentException('Provide an inspected condition and final disposition.');
            }
            $record = $returns->resolve(
                $order,
                Uuid::fromString($returnId),
                $data['disposition'],
                $data['condition'],
                $user,
                $entityManager,
            );
        } catch (\JsonException|\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['return' => $this->payload($record)]);
    }

    /** @return array<string, string|null> */
    private function payload(SalesReturn $record): array
    {
        return [
            'id' => $record->getId()->toRfc4122(),
            'itemId' => $record->getSalesOrderItem()->getId()->toRfc4122(),
            'itemName' => $record->getSalesOrderItem()->getName(),
            'sku' => $record->getSalesOrderItem()->getSku(),
            'warehouse' => $record->getWarehouse()->getName(),
            'quantity' => $record->getQuantity(),
            'reason' => $record->getReason(),
            'condition' => $record->getCondition(),
            'disposition' => $record->getDisposition(),
            'note' => $record->getNote(),
            'createdAt' => $record->getCreatedAt()->format(DATE_ATOM),
            'resolvedAt' => $record->getResolvedAt()?->format(DATE_ATOM),
        ];
    }

    private function order(string $id, EntityManagerInterface $entityManager, bool $ownerRequired = false): SalesOrder
    {
        $tenant = $this->tenant($entityManager, $ownerRequired);
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $order = $entityManager->getRepository(SalesOrder::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);
        if (!$order instanceof SalesOrder) {
            throw $this->createNotFoundException();
        }

        return $order;
    }

    private function tenant(EntityManagerInterface $entityManager, bool $ownerRequired): Tenant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $membership = $entityManager->getRepository(TenantMembership::class)->forUser($user);
        if (!$membership instanceof TenantMembership
            || ($ownerRequired && $membership->getRole() !== 'owner')) {
            throw $this->createAccessDeniedException();
        }

        return $membership->getTenant();
    }
}
