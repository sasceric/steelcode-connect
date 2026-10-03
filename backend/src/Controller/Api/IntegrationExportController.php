<?php

namespace App\Controller\Api;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Integration\CatalogueExportReferences;
use App\Integration\CatalogueExportSettings;
use App\Message\ExportShopwareCatalogue;
use App\Message\ExportWooCommerceCatalogue;
use App\Service\CatalogueSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/integrations/{id}/exports')]
final class IntegrationExportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly CatalogueExportReferences $references,
        private readonly MessageBusInterface $bus,
    )
    {
    }

    #[Route('/configuration', methods: ['GET'])]
    public function configuration(string $id): JsonResponse
    {
        $connection = $this->connection($id);

        return $this->json(['settings' => CatalogueExportSettings::normalize($connection->getConfiguration()['exportSettings'] ?? [], $connection->getConnectorKey())]);
    }

    #[Route('/configuration', methods: ['PUT'])]
    public function save(string $id, Request $request): JsonResponse
    {
        $connection = $this->connection($id);
        try {
            $settings = CatalogueExportSettings::normalize($request->toArray(), $connection->getConnectorKey());
            $this->references->validateLocalSettings($connection, $this->manager, $settings);
            if ($connection->getConnectorKey() === 'shopware' && $settings['automaticSync'] && $settings['salesChannelId'] === '') {
                throw new \InvalidArgumentException('Automatic sync requires a destination sales channel.');
            }
        } catch (\InvalidArgumentException|\JsonException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
        $configuration = $connection->getConfiguration();
        $configuration['exportSettings'] = $settings;
        $connection->updateConfiguration($configuration);
        $this->manager->flush();
        $this->manager->getConnection()->executeStatement(
            'UPDATE integration_catalogue_sync_state SET scanned_at = NULL WHERE tenant_id = :tenant AND connection_id = :connection',
            ['tenant' => (string) $connection->getTenant()->getId(), 'connection' => (string) $connection->getId()],
        );

        return $this->json(['settings' => $settings]);
    }

    #[Route('/sync', methods: ['POST'])]
    public function sync(string $id, Request $request, CatalogueSyncService $sync): JsonResponse
    {
        $connection = $this->connection($id);
        if (($request->toArray()['confirmed'] ?? false) !== true) {
            return $this->json(['message' => 'Confirm the saved catalogue scope before syncing.'], 422);
        }
        try {
            return $this->json($sync->queue($connection), 202);
        } catch (\DomainException|\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], 409);
        }
    }

    #[Route('/references/{side}/{type}', methods: ['GET'])]
    public function references(string $id, string $side, string $type, Request $request): JsonResponse
    {
        $connection = $this->connection($id);
        $page = max(1, $request->query->getInt('page', 1));
        $search = mb_substr(trim($request->query->getString('search')), 0, 200);
        try {
            $ids = array_values(array_filter(explode(',', $request->query->getString('ids'))));
            if (count($ids) > 25) {
                throw new \InvalidArgumentException('Reference lookup is limited to 25 IDs.');
            }
            foreach ($ids as $referenceId) {
                if (($side === 'local' && !Uuid::isValid($referenceId)) || ($side === 'target' && !CatalogueExportSettings::validTarget($type, $referenceId, $connection->getConnectorKey()))) {
                    throw new \InvalidArgumentException('Invalid reference ID.');
                }
            }
            $response = match ($side) {
                'local' => $this->references->localPage($connection, $this->manager, $type, $page, $search, $ids),
                'target' => $this->references->targetPage($connection, $this->manager, $type, $page, $search, $ids),
                default => throw new \InvalidArgumentException('Invalid reference side.'),
            };

            return $this->json($response);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['message' => $exception->getMessage()], 422);
        }
    }

    #[Route('/preview', methods: ['POST'])]
    public function preview(string $id): JsonResponse
    {
        $connection = $this->connection($id);
        $settings = CatalogueExportSettings::normalize($connection->getConfiguration()['exportSettings'] ?? [], $connection->getConnectorKey());
        if ($connection->getConnectorKey() === 'shopware' && $settings['salesChannelId'] === '') {
            return $this->json(['message' => 'Choose a destination sales channel first.'], 422);
        }
        $this->references->validateLocalSettings($connection, $this->manager, $settings);
        $database = $this->manager->getConnection();
        $database->beginTransaction();
        try {
            $database->executeQuery('SELECT id FROM integration_connections WHERE id = :id FOR UPDATE', ['id' => $id]);
            if ($this->activeRun($connection)) {
                $database->rollBack();

                return $this->json(['message' => 'Wait for the active integration run or cancel it first.'], 409);
            }
            $run = new IntegrationImportRun($connection->getTenant(), $connection, 'export_preview');
            $this->manager->persist($run);
            $this->manager->flush();
            $planId = (string) Uuid::v7();
            $database->insert('integration_export_plans', [
                'id' => $planId, 'tenant_id' => (string) $connection->getTenant()->getId(),
                'connection_id' => $id, 'run_id' => (string) $run->getId(),
                'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
                'settings_hash' => CatalogueExportSettings::hash($settings, $connection->getConnectorKey()),
            ]);
            $this->dispatch($connection, $planId, $run, true);
            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }

        return $this->json(['planId' => $planId, 'runId' => (string) $run->getId()], 202);
    }

    #[Route('/plans/latest', methods: ['GET'])]
    public function latest(string $id, Request $request): JsonResponse
    {
        $connection = $this->connection($id, false);
        $health = $this->manager->getConnection()->fetchAssociative(<<<'SQL'
SELECT COUNT(*) AS pending_changes,
    COALESCE(EXTRACT(EPOCH FROM CURRENT_TIMESTAMP - MIN(first_changed_at)), 0)::int AS oldest_seconds
FROM integration_catalogue_dirty WHERE tenant_id = :tenant AND connection_id = :connection
SQL, ['tenant' => (string) $connection->getTenant()->getId(), 'connection' => $id]);
        $syncHealth = [
            'pendingChanges' => (int) $health['pending_changes'],
            'oldestSeconds' => max(0, (int) $health['oldest_seconds']),
        ];
        $plan = $this->manager->getConnection()->fetchAssociative(
            'SELECT * FROM integration_export_plans WHERE connection_id = :id AND tenant_id = :tenant ORDER BY created_at DESC, id DESC LIMIT 1',
            ['id' => $id, 'tenant' => (string) $connection->getTenant()->getId()],
        );
        if (!$plan) {
            return $this->json(['plan' => null, 'items' => [], 'total' => 0, 'syncHealth' => $syncHealth]);
        }
        $run = $this->manager->getRepository(IntegrationImportRun::class)->findOneBy([
            'id' => Uuid::fromString($plan['run_id']),
            'tenant' => $connection->getTenant(),
            'connection' => $connection,
        ]);
        if ($run instanceof IntegrationImportRun && in_array($run->getStatus(), ['cancelled', 'failed'], true)
            && in_array($plan['status'], ['preparing', 'publishing'], true)) {
            // Queue cancellation/failure can happen before the handler ever starts.
            $plan['status'] = $run->getStatus();
        }
        $progress = $run instanceof IntegrationImportRun ? [
            'id' => (string) $run->getId(),
            'status' => $run->getStatus(),
            'currentStage' => $run->getCurrentStage(),
            'totalItems' => $run->getTotalItems(),
            'processedItems' => $run->getProcessedItems(),
            'failedItems' => $run->getFailedItems(),
            'failureReason' => $run->getFailureReason(),
            'createdAt' => $run->getCreatedAt()->format(DATE_ATOM),
            'startedAt' => $run->getStartedAt()?->format(DATE_ATOM),
            'updatedAt' => $run->getUpdatedAt()->format(DATE_ATOM),
            'completedAt' => $run->getCompletedAt()?->format(DATE_ATOM),
        ] : null;
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 25)));
        $items = $this->manager->getConnection()->fetchAllAssociative(
            'SELECT product_id AS id, parent_id, external_id, name, sku, action, status, issues, result, payload FROM integration_export_items WHERE plan_id = :id ORDER BY parent_id NULLS FIRST, product_id LIMIT :limit OFFSET :offset',
            ['id' => $plan['id'], 'limit' => $limit, 'offset' => ($page - 1) * $limit],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER, 'offset' => \Doctrine\DBAL\ParameterType::INTEGER],
        );
        foreach ($items as &$item) {
            $item['issues'] = json_decode($item['issues'], true);
            $built = json_decode($item['payload'], true);
            $item['fields'] = array_keys($built['payload']);
            $item['changes'] = $built['payload'];
            $item['dependencies'] = $built['dependencies'] ?? [];
            unset($item['payload']);
        }
        $summary = $this->manager->getConnection()->fetchAssociative(
            "SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE action = 'create') AS creates, COUNT(*) FILTER (WHERE action = 'update') AS updates, COUNT(*) FILTER (WHERE issues::jsonb <> '[]'::jsonb) AS blocked FROM integration_export_items WHERE plan_id = :id",
            ['id' => $plan['id']],
        );
        $plan['stale'] = $plan['settings_hash'] !== CatalogueExportSettings::hash($connection->getConfiguration()['exportSettings'] ?? [], $connection->getConnectorKey());
        unset($plan['settings'], $plan['settings_hash'], $plan['tenant_id'], $plan['connection_id']);

        return $this->json([
            'plan' => $plan,
            'run' => $progress,
            'items' => $items,
            'total' => (int) $summary['total'],
            'summary' => $summary,
            'syncHealth' => $syncHealth,
        ]);
    }

    #[Route('/plans/{planId}/publish', methods: ['POST'])]
    public function publish(string $id, string $planId, Request $request): JsonResponse
    {
        $connection = $this->connection($id);
        if (!Uuid::isValid($planId)) {
            throw $this->createNotFoundException();
        }
        $database = $this->manager->getConnection();
        $database->beginTransaction();
        try {
            $database->executeQuery('SELECT id FROM integration_connections WHERE id = :id FOR UPDATE', ['id' => $id]);
            $plan = $database->fetchAssociative(
                'SELECT * FROM integration_export_plans WHERE id = :plan AND connection_id = :id AND tenant_id = :tenant FOR UPDATE',
                ['plan' => $planId, 'id' => $id, 'tenant' => (string) $connection->getTenant()->getId()],
            );
            if (!$plan) {
                throw $this->createNotFoundException();
            }
            $confirmed = ($request->toArray()['confirmed'] ?? false) === true;
            if (!$confirmed || $plan['status'] !== 'ready' || $this->activeRun($connection)
                || $plan['settings_hash'] !== CatalogueExportSettings::hash($connection->getConfiguration()['exportSettings'] ?? [], $connection->getConnectorKey())
                || new \DateTimeImmutable($plan['created_at']) < new \DateTimeImmutable('-1 day')) {
                $database->rollBack();

                return $this->json(['message' => 'Confirm a fresh, successful preview before publishing.'], 409);
            }
            $run = new IntegrationImportRun($connection->getTenant(), $connection, 'export');
            $this->manager->persist($run);
            $this->manager->flush();
            $database->update('integration_export_plans', [
                'status' => 'publishing',
                'run_id' => (string) $run->getId(),
                'work_phase' => null,
                'work_token' => null,
                'selection_cursor' => null,
                'retry_count' => 0,
            ], ['id' => $planId]);
            $this->dispatch($connection, $planId, $run, false);
            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }

        return $this->json(['runId' => (string) $run->getId()], 202);
    }

    private function dispatch(
        IntegrationConnection $connection,
        string $planId,
        IntegrationImportRun $run,
        bool $preview,
    ): void
    {
        $class = $connection->getConnectorKey() === 'woocommerce' ? ExportWooCommerceCatalogue::class : ExportShopwareCatalogue::class;
        $this->bus->dispatch(new $class(
            (string) $connection->getTenant()->getId(), (string) $connection->getId(),
            $planId, (string) $run->getId(), $preview,
        ));
    }

    private function activeRun(IntegrationConnection $connection): bool
    {
        return $this->manager->getRepository(IntegrationImportRun::class)->findOneBy([
            'tenant' => $connection->getTenant(), 'connection' => $connection, 'status' => ['queued', 'running'],
        ]) instanceof IntegrationImportRun;
    }

    private function connection(string $id, bool $ownerRequired = true): IntegrationConnection
    {
        $user = $this->getUser();
        if (!$user instanceof User || !Uuid::isValid($id)) {
            throw $this->createAccessDeniedException();
        }
        $membership = $this->manager->getRepository(TenantMembership::class)->forUser($user);
        if (!$membership instanceof TenantMembership || ($ownerRequired && $membership->getRole() !== 'owner')) {
            throw $this->createAccessDeniedException();
        }
        $connection = $this->manager->getRepository(IntegrationConnection::class)->findOneBy(['tenant' => $membership->getTenant(), 'id' => Uuid::fromString($id)]);
        if (!$connection instanceof IntegrationConnection) {
            throw $this->createNotFoundException();
        }
        if (!in_array($connection->getConnectorKey(), ['shopware', 'woocommerce'], true) || !$connection->isEnabled() || $connection->getStatus() !== 'active' || !in_array('channel', $connection->getDirections(), true)) {
            throw $this->createAccessDeniedException('Catalogue publication requires an active supported channel.');
        }

        return $connection;
    }
}
