<?php

namespace App\Service;

use App\Entity\InventorySyncOutbox;
use App\Entity\IntegrationConnection;
use App\Entity\Product;
use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;

final class InventorySyncOutboxService
{
    public function queuePublishedProductsForConnection(
        IntegrationConnection $connection,
        EntityManagerInterface $entityManager,
    ): void {
        $entityManager->getConnection()->executeStatement(
            <<<'SQL'
INSERT INTO inventory_sync_outbox (id, tenant_id, product_id, status, created_at, updated_at)
SELECT gen_random_uuid(), publications.tenant_id, publications.product_id, 'pending',
       NOW() AT TIME ZONE 'UTC', NOW() AT TIME ZONE 'UTC'
FROM (
    SELECT DISTINCT publication.tenant_id, publication.product_id
    FROM product_channel_publications publication
    INNER JOIN integration_sales_channels channel ON channel.id = publication.sales_channel_id
    WHERE channel.connection_id = :connectionId
      AND channel.tenant_id = :tenantId
      AND publication.tenant_id = :tenantId
      AND channel.active = TRUE
      AND publication.visibility > 0
      AND NOT EXISTS (
          SELECT 1 FROM products variant WHERE variant.parent_id = publication.product_id
      )
) publications
ON CONFLICT (tenant_id, product_id)
DO UPDATE SET status = 'pending', last_error = NULL, updated_at = EXCLUDED.updated_at
SQL,
            [
                'connectionId' => $connection->getId()->toRfc4122(),
                'tenantId' => $connection->getTenant()->getId()->toRfc4122(),
            ],
        );
    }

    public function queue(
        Tenant $tenant,
        Product $product,
        EntityManagerInterface $entityManager,
    ): void {
        foreach ($entityManager->getUnitOfWork()->getScheduledEntityInsertions() as $scheduled) {
            if (
                $scheduled instanceof InventorySyncOutbox
                && $scheduled->getTenant()->getId() == $tenant->getId()
                && $scheduled->getProduct()->getId() == $product->getId()
            ) {
                $scheduled->queue();

                return;
            }
        }

        $event = $entityManager->getRepository(InventorySyncOutbox::class)->findOneBy([
            'tenant' => $tenant,
            'product' => $product,
        ]);
        if (!$event instanceof InventorySyncOutbox) {
            $entityManager->persist(new InventorySyncOutbox($tenant, $product));

            return;
        }

        $event->queue();
    }
}
