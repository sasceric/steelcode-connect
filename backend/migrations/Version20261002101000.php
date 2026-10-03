<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002101000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restore missing channel stock identities only from unambiguous tenant/connection product mappings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
WITH identities AS (
    SELECT tenant_id, connection_id, local_id, MIN(external_id) AS external_id
    FROM integration_entity_mappings
    WHERE entity_type = 'product' AND external_id <> ''
    GROUP BY tenant_id, connection_id, local_id
    HAVING COUNT(DISTINCT external_id) = 1
)
UPDATE product_channel_publications p SET external_product_id = i.external_id
FROM integration_sales_channels c, integration_connections connection, identities i
WHERE p.sales_channel_id = c.id AND p.tenant_id = c.tenant_id
    AND connection.id = c.connection_id AND connection.tenant_id = p.tenant_id
    AND connection.connector_key IN ('shopware', 'woocommerce')
    AND i.tenant_id = p.tenant_id AND i.connection_id = c.connection_id AND i.local_id = p.product_id
    AND EXISTS (SELECT 1 FROM products product WHERE product.id = p.product_id AND product.tenant_id = p.tenant_id)
    AND ((connection.connector_key = 'shopware' AND i.external_id ~ '^[a-fA-F0-9]{32}$')
        OR (connection.connector_key = 'woocommerce' AND i.external_id ~ '^[1-9][0-9]*$'))
    AND p.external_product_id IS NULL
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Restored destination identities must not be cleared again.');
    }
}
