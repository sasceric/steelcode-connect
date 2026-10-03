<?php

namespace App\Controller\Api;

use App\Entity\SalesOrder;
use App\Entity\SalesPickTask;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Service\SalesPickTaskService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/sales/orders/{id}/pick-task')]
final class SalesPickTaskController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function show(
        string $id,
        EntityManagerInterface $entityManager,
        SalesPickTaskService $picking,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager);
        $order = $this->order($id, $tenant, $entityManager);

        return $this->json([
            'pickTask' => $this->payload($picking->latest($order, $entityManager), $picking),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function start(
        string $id,
        EntityManagerInterface $entityManager,
        SalesPickTaskService $picking,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
        $order = $this->order($id, $tenant, $entityManager);
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        try {
            $task = $picking->start($order, $user, $entityManager);
        } catch (\DomainException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['pickTask' => $this->payload($task, $picking)]);
    }

    #[Route('', methods: ['PATCH'])]
    public function record(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        SalesPickTaskService $picking,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
        $order = $this->order($id, $tenant, $entityManager);
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        try {
            $data = $request->toArray();
            if (!is_string($data['taskId'] ?? null)
                || !Uuid::isValid($data['taskId'])
                || !is_int($data['version'] ?? null)
                || $data['version'] < 1
                || !is_bool($data['complete'] ?? null)
                || !is_array($data['quantities'] ?? null)) {
                throw new \InvalidArgumentException('Provide task ID, version, picked quantities, and completion flag.');
            }
            $task = $picking->record(
                $order,
                Uuid::fromString($data['taskId']),
                $data['quantities'],
                $data['version'],
                $data['complete'],
                $user,
                $entityManager,
            );
        } catch (\JsonException|\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['pickTask' => $this->payload($task, $picking)]);
    }

    /** @return array<string, mixed>|null */
    private function payload(?SalesPickTask $task, SalesPickTaskService $picking): ?array
    {
        if (!$task instanceof SalesPickTask) {
            return null;
        }

        return [
            'id' => $task->getId()->toRfc4122(),
            'status' => $task->getStatus(),
            'stale' => $picking->isStale($task),
            'warehouse' => $task->getWarehouse()->getName(),
            'lines' => $task->getLines(),
            'pickedQuantities' => $task->getPickedQuantities(),
            'version' => $task->getVersion(),
            'createdAt' => $task->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $task->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function order(string $id, Tenant $tenant, EntityManagerInterface $entityManager): SalesOrder
    {
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

    private function tenant(EntityManagerInterface $entityManager, bool $ownerRequired = false): Tenant
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
