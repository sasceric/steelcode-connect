<?php

namespace App\MessageHandler;

use App\Message\ReconcileShopwareCatalogue;
use App\Integration\IntegrationRetryLaterException;
use App\Service\CatalogueSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final class ReconcileShopwareCatalogueHandler
{
    public function __construct(
        private readonly CatalogueSyncService $sync,
        private readonly EntityManagerInterface $manager,
    )
    {
    }

    public function __invoke(ReconcileShopwareCatalogue $message): void
    {
        if (!Uuid::isValid($message->tenantId) || !Uuid::isValid($message->connectionId)) {
            return;
        }
        // Keep the lease on failure: Messenger retries this delivery instead of
        // every scheduler tick adding another job for the unavailable shop.
        try {
            $this->sync->scan($message->tenantId, $message->connectionId);
        } catch (IntegrationRetryLaterException $exception) {
            $this->manager->getConnection()->executeStatement(
                "UPDATE integration_catalogue_sync_state SET queued_until = GREATEST(queued_until, CURRENT_TIMESTAMP + (:seconds * INTERVAL '1 second')) WHERE tenant_id = :tenant AND connection_id = :connection",
                [
                    'seconds' => min(3600, max(1, $exception->delaySeconds)) + 60,
                    'tenant' => $message->tenantId,
                    'connection' => $message->connectionId,
                ],
            );
            throw $exception;
        }
        $this->manager->getConnection()->executeStatement(
            'UPDATE integration_catalogue_sync_state SET queued_until = NULL WHERE tenant_id = :tenant AND connection_id = :connection',
            ['tenant' => $message->tenantId, 'connection' => $message->connectionId],
        );
    }
}
