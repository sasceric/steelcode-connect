<?php

namespace App\Controller\Api;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Integration\IntegrationImportLogger;
use App\Integration\IntegrationImportLogReader;
use App\Message\ImportShopwareProducts;
use App\Message\ImportShopwareSales;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/integrations')]
final class IntegrationImportController extends AbstractController
{
    #[Route('/imports/active', methods: ['GET'])]
    public function active(EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $runs = $entityManager->getRepository(IntegrationImportRun::class)->findBy(
            [
                'tenant' => $tenant,
                'status' => ['queued', 'running'],
            ],
            ['createdAt' => 'DESC'],
        );

        return $this->json([
            'runs' => array_map($this->payload(...), $runs),
        ]);
    }

    #[Route('/{id}/imports', methods: ['GET'])]
    public function index(
        string $id,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $connection = $this->connection($id, $entityManager);
        $runs = $entityManager->getRepository(IntegrationImportRun::class)->findBy(
            ['connection' => $connection],
            ['createdAt' => 'DESC'],
            10,
        );

        return $this->json([
            'runs' => array_map($this->payload(...), $runs),
        ]);
    }

    #[Route('/{id}/imports/{runId}/logs', methods: ['GET'])]
    public function logs(
        string $id,
        string $runId,
        EntityManagerInterface $entityManager,
        IntegrationImportLogReader $logReader,
    ): JsonResponse {
        $connection = $this->connection($id, $entityManager);
        if (!Uuid::isValid($runId)) {
            throw $this->createNotFoundException();
        }

        $run = $entityManager->getRepository(IntegrationImportRun::class)->findOneBy([
            'id' => Uuid::fromString($runId),
            'connection' => $connection,
        ]);
        if (!$run instanceof IntegrationImportRun) {
            throw $this->createNotFoundException();
        }

        return $this->json([
            'logs' => $logReader->forRun($run),
        ]);
    }

    #[Route('/{id}/imports/products', methods: ['POST'])]
    public function queueProducts(
        string $id,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        TranslatorInterface $translator,
        IntegrationImportLogger $importLogger,
    ): JsonResponse {
        $connection = $this->connection($id, $entityManager, true);
        if (
            $connection->getConnectorKey() !== 'shopware'
            || !$connection->isEnabled()
            || $connection->getStatus() !== 'active'
        ) {
            return $this->json([
                'message' => $this->message($translator, 'integration.import_not_available'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $activeRun = $entityManager->getRepository(IntegrationImportRun::class)
            ->findOneBy(
                [
                    'connection' => $connection,
                    'type' => 'products',
                    'status' => ['queued', 'running'],
                ],
                ['createdAt' => 'DESC'],
            );
        if ($activeRun instanceof IntegrationImportRun) {
            return $this->json([
                'message' => $this->message($translator, 'integration.import_already_running'),
                'run' => $this->payload($activeRun),
            ], Response::HTTP_CONFLICT);
        }

        $run = new IntegrationImportRun(
            $connection->getTenant(),
            $connection,
            'products',
        );
        $entityManager->persist($run);
        $importLogger->info(
            $run,
            'queued',
            'integrationLog.queued',
        );
        $entityManager->flush();

        $messageBus->dispatch(new ImportShopwareProducts($run->getId()->toRfc4122()));

        return $this->json([
            'message' => $this->message($translator, 'integration.import_queued'),
            'run' => $this->payload($run),
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/{id}/imports/sales', methods: ['POST'])]
    public function queueSales(
        string $id,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
        TranslatorInterface $translator,
        IntegrationImportLogger $importLogger,
    ): JsonResponse {
        $connection = $this->connection($id, $entityManager, true);
        $settings = $connection->getConfiguration()['importSettings'] ?? [];
        $areas = is_array($settings) && is_array($settings['areas'] ?? null) ? $settings['areas'] : [];
        if (
            $connection->getConnectorKey() !== 'shopware'
            || !in_array('channel', $connection->getDirections(), true)
            || !$connection->isEnabled()
            || $connection->getStatus() !== 'active'
            || (!(bool) ($areas['salesCustomers'] ?? false) && !(bool) ($areas['salesOrders'] ?? false))
        ) {
            return $this->json([
                'message' => $this->message($translator, 'integration.sales_import_not_available'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $activeRun = $entityManager->getRepository(IntegrationImportRun::class)->findOneBy([
            'connection' => $connection,
            'type' => 'sales',
            'status' => ['queued', 'running'],
        ]);
        if ($activeRun instanceof IntegrationImportRun) {
            return $this->json([
                'message' => $this->message($translator, 'integration.sales_import_already_running'),
                'run' => $this->payload($activeRun),
            ], Response::HTTP_CONFLICT);
        }

        $run = new IntegrationImportRun($connection->getTenant(), $connection, 'sales');
        $entityManager->persist($run);
        $importLogger->info($run, 'queued', 'integrationLog.salesQueued');
        $entityManager->flush();
        $messageBus->dispatch(new ImportShopwareSales($run->getId()->toRfc4122()));

        return $this->json([
            'message' => $this->message($translator, 'integration.sales_import_queued'),
            'run' => $this->payload($run),
        ], Response::HTTP_ACCEPTED);
    }

    #[Route('/{id}/imports/{runId}/cancel', methods: ['POST'])]
    public function cancel(
        string $id,
        string $runId,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        IntegrationImportLogger $importLogger,
    ): JsonResponse {
        $connection = $this->connection($id, $entityManager, true);
        if (!Uuid::isValid($runId)) {
            throw $this->createNotFoundException();
        }

        $run = $entityManager->getRepository(IntegrationImportRun::class)
            ->findOneBy([
                'id' => Uuid::fromString($runId),
                'connection' => $connection,
            ]);
        if (!$run instanceof IntegrationImportRun) {
            throw $this->createNotFoundException();
        }

        if (!in_array($run->getStatus(), ['queued', 'running'], true)) {
            return $this->json([
                'message' => $this->message($translator, 'integration.import_not_active'),
                'run' => $this->payload($run),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $run->cancel();
        $importLogger->info(
            $run,
            'cancelled',
            $run->getType() === 'sales' ? 'integrationLog.salesCancelled' : 'integrationLog.cancelled',
        );
        $entityManager->flush();

        return $this->json([
            'message' => $this->message($translator, 'integration.import_cancelled'),
            'run' => $this->payload($run),
        ]);
    }

    private function connection(
        string $id,
        EntityManagerInterface $entityManager,
        bool $ownerRequired = false,
    ): IntegrationConnection {
        $tenant = $this->tenant($entityManager, $ownerRequired);
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $connection = $entityManager->getRepository(IntegrationConnection::class)
            ->findOneBy([
                'id' => Uuid::fromString($id),
                'tenant' => $tenant,
            ]);
        if (!$connection instanceof IntegrationConnection) {
            throw $this->createNotFoundException();
        }

        return $connection;
    }

    private function tenant(
        EntityManagerInterface $entityManager,
        bool $ownerRequired = false,
    ): Tenant {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $membership = $entityManager->getRepository(TenantMembership::class)
            ->findOneBy(['user' => $user]);
        if (
            !$membership instanceof TenantMembership
            || ($ownerRequired && $membership->getRole() !== 'owner')
        ) {
            throw $this->createAccessDeniedException();
        }

        return $membership->getTenant();
    }

    private function message(TranslatorInterface $translator, string $key): string
    {
        $user = $this->getUser();

        return $translator->trans(
            $key,
            locale: $user instanceof User ? $user->getLocale() : 'bs',
        );
    }

    /** @return array<string, int|string|null> */
    private function payload(IntegrationImportRun $run): array
    {
        return [
            'id' => $run->getId()->toRfc4122(),
            'connectionId' => $run->getConnection()->getId()->toRfc4122(),
            'connectionName' => $run->getConnection()->getName(),
            'connectorKey' => $run->getConnection()->getConnectorKey(),
            'type' => $run->getType(),
            'status' => $run->getStatus(),
            'currentStage' => $run->getCurrentStage(),
            'totalItems' => $run->getTotalItems(),
            'processedItems' => $run->getProcessedItems(),
            'createdItems' => $run->getCreatedItems(),
            'updatedItems' => $run->getUpdatedItems(),
            'failedItems' => $run->getFailedItems(),
            'failureReason' => $run->getFailureReason(),
            'createdAt' => $run->getCreatedAt()->format(DATE_ATOM),
            'startedAt' => $run->getStartedAt()?->format(DATE_ATOM),
            'completedAt' => $run->getCompletedAt()?->format(DATE_ATOM),
        ];
    }
}
