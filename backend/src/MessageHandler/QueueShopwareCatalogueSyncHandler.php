<?php

namespace App\MessageHandler;

use App\Message\QueueShopwareCatalogueSync;
use App\Message\ReconcileShopwareCatalogue;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class QueueShopwareCatalogueSyncHandler
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $bus,
    )
    {
    }

    public function __invoke(QueueShopwareCatalogueSync $message): void
    {
        $database = $this->manager->getConnection();
        // Database-only dispatch: a slow shop never blocks scheduling for other tenants.
        $rows = $database->fetchAllAssociative(<<<'SQL'
SELECT c.tenant_id, c.id FROM integration_connections c
LEFT JOIN integration_catalogue_sync_state s ON s.connection_id = c.id AND s.tenant_id = c.tenant_id
WHERE c.connector_key IN ('shopware', 'woocommerce') AND c.enabled = TRUE AND c.status = 'active'
    AND c.configuration->'exportSettings'->>'automaticSync' = 'true'
    AND c.directions::jsonb @> '["channel"]'::jsonb
    AND (s.queued_until IS NULL OR s.queued_until < CURRENT_TIMESTAMP)
    AND NOT EXISTS (SELECT 1 FROM integration_import_runs r WHERE r.tenant_id = c.tenant_id
        AND r.connection_id = c.id AND r.status IN ('queued', 'running'))
    AND (((s.scanned_at IS NULL OR s.scanned_at < CURRENT_TIMESTAMP - INTERVAL '1 minute')
        AND NOT EXISTS (SELECT 1 FROM integration_catalogue_dirty d WHERE d.tenant_id = c.tenant_id AND d.connection_id = c.id))
        OR EXISTS (SELECT 1 FROM integration_catalogue_dirty d WHERE d.tenant_id = c.tenant_id
            AND d.connection_id = c.id AND (d.changed_at <= CURRENT_TIMESTAMP - INTERVAL '5 seconds'
                OR d.first_changed_at <= CURRENT_TIMESTAMP - INTERVAL '30 seconds')))
ORDER BY s.queued_at NULLS FIRST, c.tenant_id, c.id LIMIT 25
SQL);
        foreach ($rows as $row) {
            $database->beginTransaction();
            try {
                $claimed = $database->executeStatement(<<<'SQL'
INSERT INTO integration_catalogue_sync_state (tenant_id, connection_id, settings_hash, queued_until, queued_at)
VALUES (:tenant, :connection, '', CURRENT_TIMESTAMP + INTERVAL '10 minutes', CURRENT_TIMESTAMP)
ON CONFLICT (connection_id) DO UPDATE SET queued_until = EXCLUDED.queued_until, queued_at = EXCLUDED.queued_at
WHERE integration_catalogue_sync_state.tenant_id = EXCLUDED.tenant_id
    AND (integration_catalogue_sync_state.queued_until IS NULL OR integration_catalogue_sync_state.queued_until < CURRENT_TIMESTAMP)
SQL, ['tenant' => $row['tenant_id'], 'connection' => $row['id']]);
                if ($claimed) {
                    $this->bus->dispatch(new ReconcileShopwareCatalogue($row['tenant_id'], $row['id']));
                }
                $database->commit();
            } catch (\Throwable $exception) {
                $database->rollBack();
                $this->logger->error('Catalogue reconciliation failed.', [
                    'tenantId' => $row['tenant_id'],
                    'connectionId' => $row['id'],
                    'exception' => $exception,
                ]);
            }
        }
    }
}
