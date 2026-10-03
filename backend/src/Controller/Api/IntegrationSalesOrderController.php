<?php

namespace App\Controller\Api;

use App\Entity\IntegrationConnection;
use App\Entity\SalesOrder;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Service\SalesOrderIngestionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/integrations')]
final class IntegrationSalesOrderController extends AbstractController
{
    #[Route('/{id}/orders/events', methods: ['POST'])]
    public function ingest(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        SalesOrderIngestionService $orders,
    ): JsonResponse {
        $connection = $this->connection($id, $entityManager);
        try {
            $event = $request->toArray();
            $order = $orders->ingest($connection, $event, $entityManager);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['order' => $this->payload($order)]);
    }

    private function connection(string $id, EntityManagerInterface $entityManager): IntegrationConnection
    {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $connection = $entityManager->getRepository(IntegrationConnection::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $this->tenant($entityManager),
        ]);
        if (!$connection instanceof IntegrationConnection) {
            throw $this->createNotFoundException();
        }

        return $connection;
    }

    private function tenant(EntityManagerInterface $entityManager): Tenant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $membership = $entityManager->getRepository(TenantMembership::class)->forUser($user);
        if (!$membership instanceof TenantMembership || $membership->getRole() !== 'owner') {
            throw $this->createAccessDeniedException();
        }

        return $membership->getTenant();
    }

    /** @return array<string, string|null> */
    private function payload(SalesOrder $order): array
    {
        return [
            'id' => $order->getId()->toRfc4122(),
            'externalId' => $order->getExternalId(),
            'externalNumber' => $order->getExternalNumber(),
            'status' => $order->getStatus(),
        ];
    }
}
