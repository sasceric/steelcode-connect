<?php

namespace App\Controller\Api;

use App\Entity\InventorySyncOutbox;
use App\Entity\IntegrationConnection;
use App\Entity\SalesOrder;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Service\OperationsExceptionService;
use App\Service\SalesOrderIngestionService;
use App\Message\SyncShopwareSalesConnection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

#[Route('/api/v1/operations/exceptions')]
final class OperationsExceptionController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        OperationsExceptionService $exceptions,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager);
        $type = (string) $request->query->get('type', 'unmatched_products');
        if (!in_array($type, OperationsExceptionService::TYPES, true)) {
            return $this->json(['message' => 'Unknown exception type.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($exceptions->list(
            $tenant,
            $type,
            $request->query->getInt('page', 1),
            $request->query->getInt('limit', 25),
            $entityManager,
        ));
    }

    #[Route('/orders/{id}/retry-allocation', methods: ['POST'])]
    public function retryAllocation(
        string $id,
        EntityManagerInterface $entityManager,
        SalesOrderIngestionService $orders,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
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

        try {
            $allocated = $orders->retryAllocation($order, $entityManager);
        } catch (\DomainException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_CONFLICT);
        }
        if (!$allocated) {
            return $this->json([
                'message' => 'The order still has unmatched products or insufficient warehouse stock.',
            ], Response::HTTP_CONFLICT);
        }

        return $this->json(['status' => $order->getStatus()]);
    }

    #[Route('/stock-sync/{id}/retry', methods: ['POST'])]
    public function retryStockSync(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $event = $entityManager->getRepository(InventorySyncOutbox::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);
        if (!$event instanceof InventorySyncOutbox) {
            throw $this->createNotFoundException();
        }

        $database = $entityManager->getConnection();
        $database->beginTransaction();
        try {
            $entityManager->lock($event, LockMode::PESSIMISTIC_WRITE);
            $entityManager->refresh($event);
            if ($event->getStatus() === 'failed') {
                $event->queue();
                $entityManager->flush();
            }
            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();

            throw $exception;
        }

        return $this->json(['status' => $event->getStatus()]);
    }

    #[Route('/sales-sync/{id}/retry', methods: ['POST'])]
    public function retrySalesSync(
        string $id,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $connection = $entityManager->getRepository(IntegrationConnection::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $tenant,
        ]);
        if (!$connection instanceof IntegrationConnection) {
            throw $this->createNotFoundException();
        }
        $settings = $connection->getConfiguration()['importSettings'] ?? [];
        if ($connection->getConnectorKey() !== 'shopware'
            || !$connection->isEnabled()
            || $connection->getStatus() !== 'active'
            || !is_array($settings)
            || ($settings['salesContinuousSync'] ?? false) !== true) {
            return $this->json([
                'message' => 'Enable continuous Shopware Sales sync before retrying.',
            ], Response::HTTP_CONFLICT);
        }

        $bus->dispatch(new SyncShopwareSalesConnection($id));

        return $this->json(['status' => 'queued']);
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
