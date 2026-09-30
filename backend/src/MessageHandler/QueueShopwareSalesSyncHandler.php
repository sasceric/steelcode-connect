<?php

namespace App\MessageHandler;

use App\Message\QueueShopwareSalesSync;
use App\Message\SyncShopwareSalesConnection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class QueueShopwareSalesSyncHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(QueueShopwareSalesSync $message): void
    {
        $connections = $this->entityManager->getConnection()->executeQuery(
            <<<'SQL'
SELECT id
FROM integration_connections
WHERE connector_key = 'shopware'
  AND enabled = TRUE
  AND status = 'active'
  AND configuration->'importSettings'->>'salesContinuousSync' = 'true'
  AND configuration->'importSettings'->>'salesContinuousStartedAt' IS NOT NULL
  AND configuration->'importSettings'->'areas'->>'salesOrders' = 'true'
  AND directions::jsonb @> '["channel"]'::jsonb
SQL,
        );

        while (($id = $connections->fetchOne()) !== false) {
            if (is_string($id)) {
                $this->bus->dispatch(new SyncShopwareSalesConnection($id));
            }
        }
    }
}
