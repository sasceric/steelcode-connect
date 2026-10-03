<?php

namespace App\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationEntityMapping;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

final class WooCommerceStockPublisher
{
    public function __construct(private readonly WooCommerceClient $client)
    {
    }

    public function hasParentStockPools(
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
    ): bool
    {
        return (bool) $manager->getConnection()->fetchOne(
            <<<'SQL'
            SELECT EXISTS (
                SELECT 1 FROM integration_entity_mappings mapping
                JOIN products parent ON parent.id = mapping.local_id AND parent.tenant_id = mapping.tenant_id
                JOIN product_translations translation ON translation.product_id = parent.id
                WHERE mapping.connection_id = :connectionId AND mapping.tenant_id = :tenantId
                  AND mapping.entity_type = 'product'
                  AND translation.custom_fields::jsonb->'_woocommerce'->>'type' = 'variable'
                  AND translation.custom_fields::jsonb->'_woocommerce'->>'manage_stock' = 'true'
                  AND EXISTS (
                      SELECT 1 FROM product_channel_publications publication
                      JOIN integration_sales_channels channel ON channel.id = publication.sales_channel_id
                      JOIN products target ON target.id = publication.product_id AND target.tenant_id = :tenantId
                      WHERE channel.connection_id = :connectionId AND channel.active = TRUE
                        AND publication.tenant_id = :tenantId AND publication.visibility > 0
                        AND (target.parent_id = parent.id OR target.id = parent.id)
                  )
            )
            SQL,
            [
                'connectionId' => (string) $connection->getId(),
                'tenantId' => (string) $connection->getTenant()->getId(),
            ],
        );
    }

    public function publish(
        IntegrationConnection $connection,
        Product $product,
        string $externalId,
        string $available,
        array $secrets,
        EntityManagerInterface $manager,
    ): void
    {
        if ($product->getTenant()->getId() != $connection->getTenant()->getId()) {
            throw new \DomainException('Cross-tenant WooCommerce stock publication is forbidden.');
        }
        $mapping = $manager->getRepository(IntegrationEntityMapping::class)->findOneBy(
            [
                'tenant' => $connection->getTenant(),
                'connection' => $connection,
                'entityType' => 'product',
                'externalId' => $externalId,
                'localId' => $product->getId(),
            ],
        );
        if (!$mapping instanceof IntegrationEntityMapping || !ctype_digit($externalId)) {
            throw new \DomainException('WooCommerce stock publication has no verified product mapping.');
        }
        $resource = 'products/' . $externalId;
        $parent = $product->getParent();
        if ($parent instanceof Product) {
            $parents = $manager->getRepository(IntegrationEntityMapping::class)->findBy(
                [
                    'tenant' => $connection->getTenant(),
                    'connection' => $connection,
                    'entityType' => 'product',
                    'localId' => $parent->getId(),
                ],
            );
            if (count($parents) !== 1 || !ctype_digit($parents[0]->getExternalId())) {
                throw new \DomainException('WooCommerce variation stock has no unambiguous parent mapping.');
            }
            $resource = 'products/' . $parents[0]->getExternalId() . '/variations/' . $externalId;
        }
        $baseUrl = (string) ($connection->getConfiguration()['baseUrl'] ?? '');
        $source = $this->client->object(
            'GET',
            $baseUrl,
            $secrets,
            $resource,
        );
        if ((string) ($source['id'] ?? '') !== $externalId || ($source['type'] ?? null) === 'variable') {
            throw new \DomainException('WooCommerce stock target is not the mapped leaf product.');
        }
        if (($source['manage_stock'] ?? false) === 'parent') {
            throw new \DomainException('WooCommerce parent-managed variation stock pools require a pool-aware adapter.');
        }
        if (($source['manage_stock'] ?? false) !== true) {
            // Preserve intentionally unmanaged / virtual products. Never enable stock
            // management or impose quantity zero merely because no local level exists.
            return;
        }
        if (!is_numeric($available) || (float) $available < 0 || (float) $available !== floor((float) $available)) {
            throw new \DomainException('Core WooCommerce stock requires a non-negative whole-unit quantity.');
        }
        $quantity = (int) $available;
        if (isset($source['stock_quantity']) && (int) $source['stock_quantity'] === $quantity) {
            return;
        }
        // An absolute assignment is replay-safe; it never deducts stock twice.
        $updated = $this->client->object(
            'PUT',
            $baseUrl,
            $secrets,
            $resource,
            ['stock_quantity' => $quantity],
        );
        if ((string) ($updated['id'] ?? '') !== $externalId || ($updated['stock_quantity'] ?? null) !== $quantity) {
            throw new \RuntimeException('WooCommerce did not confirm the published stock quantity.');
        }
    }
}
