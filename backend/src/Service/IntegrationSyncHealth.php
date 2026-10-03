<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/** Read-only operator diagnostics. Never returns secrets or customer payloads. */
final class IntegrationSyncHealth
{
    public function read(
        Connection $database,
        string $tenantId,
        string $connectionId,
        int $backlogSeconds = 120,
        int $staleRunSeconds = 900,
    ): array
    {
        if (!Uuid::isValid($tenantId) || !Uuid::isValid($connectionId)
            || $backlogSeconds < 1 || $staleRunSeconds < 1) {
            throw new \InvalidArgumentException('Valid tenant, connection and positive thresholds are required.');
        }
        $parameters = ['tenant' => $tenantId, 'connection' => $connectionId];
        $connection = $database->fetchAssociative(<<<'SQL'
SELECT c.id, c.connector_key, c.enabled, c.status,
    COALESCE(c.configuration->'exportSettings'->>'automaticSync' = 'true', false) AS automatic,
    COALESCE(c.configuration->'importSettings'->>'salesContinuousSync' = 'true', false) AS sales_continuous,
    s.scanned_at, s.queued_at, s.queued_until
FROM integration_connections c
LEFT JOIN integration_catalogue_sync_state s ON s.tenant_id = c.tenant_id AND s.connection_id = c.id
WHERE c.tenant_id = :tenant AND c.id = :connection AND c.connector_key IN ('shopware', 'woocommerce')
SQL, $parameters);
        if (!$connection) {
            throw new \DomainException('Supported connection not found in this tenant.');
        }
        $backlog = $database->fetchAssociative(<<<'SQL'
SELECT COUNT(*)::int AS pending_changes,
    GREATEST(COALESCE(EXTRACT(EPOCH FROM CURRENT_TIMESTAMP - MIN(first_changed_at)), 0), 0)::int AS oldest_seconds
FROM integration_catalogue_dirty WHERE tenant_id = :tenant AND connection_id = :connection
SQL, $parameters);
        $runs = $database->fetchAllAssociative(<<<'SQL'
SELECT r.id, r.type, r.status, r.current_stage, r.processed_items, r.total_items, r.failed_items,
    r.updated_at, p.work_phase, p.retry_count,
    GREATEST(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP AT TIME ZONE 'UTC') - r.updated_at), 0)::int AS heartbeat_age_seconds
FROM integration_import_runs r
LEFT JOIN integration_export_plans p ON p.tenant_id = r.tenant_id AND p.connection_id = r.connection_id AND p.run_id = r.id
WHERE r.tenant_id = :tenant AND r.connection_id = :connection AND r.status IN ('queued', 'running')
ORDER BY r.created_at
SQL, $parameters);
        $latest = $database->fetchAssociative(<<<'SQL'
SELECT r.id, r.status, r.failed_items, r.updated_at, p.status AS plan_status
FROM integration_import_runs r
LEFT JOIN integration_export_plans p ON p.tenant_id = r.tenant_id AND p.connection_id = r.connection_id AND p.run_id = r.id
WHERE r.tenant_id = :tenant AND r.connection_id = :connection
ORDER BY r.created_at DESC, r.id DESC LIMIT 1
SQL, $parameters) ?: null;
        $sales = $database->fetchAssociative(<<<'SQL'
SELECT s.last_synced_at, s.last_error IS NOT NULL AS has_error,
    GREATEST(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP AT TIME ZONE 'UTC') - s.last_synced_at), 0)::int AS age_seconds,
    s.last_synced_at > s.started_at AS advanced
FROM integration_sales_sync_cursors s
JOIN integration_connections c ON c.id = s.connection_id
WHERE c.tenant_id = :tenant AND c.id = :connection
SQL, $parameters) ?: null;
        // Outbox records are shared across a tenant's channels, not per connection.
        $stock = $database->fetchAssociative(<<<'SQL'
SELECT COUNT(*) FILTER (WHERE status = 'pending')::int AS pending,
    COUNT(*) FILTER (WHERE status = 'failed')::int AS failed,
    GREATEST(COALESCE(EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP AT TIME ZONE 'UTC') - MIN(updated_at) FILTER (WHERE status IN ('pending', 'failed'))), 0), 0)::int AS oldest_seconds
FROM inventory_sync_outbox WHERE tenant_id = :tenant
SQL, ['tenant' => $tenantId]);
        $alerts = [];
        if ($connection['enabled'] && $connection['status'] === 'active') {
            if ($connection['automatic'] && (int) $backlog['oldest_seconds'] >= $backlogSeconds) {
                $alerts[] = ['code' => 'catalogue_backlog_age', 'severity' => 'warning'];
            }
            foreach ($runs as $run) {
                $threshold = $run['status'] === 'queued' ? $backlogSeconds : $staleRunSeconds;
                if ((int) $run['heartbeat_age_seconds'] >= $threshold) {
                    $alerts[] = [
                        'code' => $run['status'] === 'queued' ? 'queued_run_age' : 'stale_run_heartbeat',
                        'severity' => 'warning',
                        'runId' => $run['id'],
                    ];
                }
            }
            if ($latest !== null && ($latest['status'] === 'failed'
                || (int) $latest['failed_items'] > 0
                || $latest['plan_status'] === 'blocked')) {
                $alerts[] = ['code' => 'latest_run_requires_attention', 'severity' => 'critical'];
            }
            if ($connection['sales_continuous'] && ($sales === null || $sales['has_error']
                || !$sales['advanced'] || (int) $sales['age_seconds'] >= 300)) {
                $alerts[] = ['code' => 'sales_checkpoint_not_fresh', 'severity' => 'critical'];
            }
        }
        if ((int) $stock['failed'] > 0) {
            $alerts[] = ['code' => 'tenant_stock_outbox_failed', 'severity' => 'critical'];
        } elseif ((int) $stock['pending'] > 0 && (int) $stock['oldest_seconds'] >= $backlogSeconds) {
            $alerts[] = ['code' => 'tenant_stock_outbox_age', 'severity' => 'warning'];
        }
        $status = $alerts === [] ? 'ok' : 'warning';
        foreach ($alerts as $alert) {
            if ($alert['severity'] === 'critical') {
                $status = 'critical';
                break;
            }
        }

        return [
            'tenantId' => $tenantId,
            'checkedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            'health' => $status,
            'alerts' => $alerts,
            'connection' => $connection,
            'backlog' => $backlog,
            'activeRuns' => $runs,
            'latestRun' => $latest,
            'salesCheckpoint' => $sales,
            'stockOutbox' => ['scope' => 'tenant', ...$stock],
            'thresholds' => [
                'backlogSeconds' => $backlogSeconds,
                'staleRunSeconds' => $staleRunSeconds,
                'salesCheckpointSeconds' => 300,
            ],
        ];
    }
}
