<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tenant-scoped frozen catalogue export previews and publication results.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE integration_export_plans (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    connection_id UUID NOT NULL REFERENCES integration_connections(id) ON DELETE CASCADE,
    run_id UUID NOT NULL REFERENCES integration_import_runs(id) ON DELETE CASCADE,
    settings JSON NOT NULL,
    settings_hash VARCHAR(64) NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'preparing',
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        $this->addSql('CREATE INDEX idx_export_plan_connection ON integration_export_plans (tenant_id, connection_id, created_at)');
        $this->addSql(<<<'SQL'
CREATE TABLE integration_export_items (
    id UUID PRIMARY KEY,
    plan_id UUID NOT NULL REFERENCES integration_export_plans(id) ON DELETE CASCADE,
    product_id UUID NOT NULL,
    parent_id UUID DEFAULT NULL,
    external_id VARCHAR(32) NOT NULL,
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(255) NOT NULL,
    action VARCHAR(16) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    payload JSON NOT NULL,
    source_hash VARCHAR(64) NOT NULL,
    target_hash VARCHAR(64) DEFAULT NULL,
    issues JSON NOT NULL,
    result TEXT DEFAULT NULL,
    UNIQUE (plan_id, product_id)
)
SQL);
        $this->addSql('CREATE INDEX idx_export_items_page ON integration_export_items (plan_id, parent_id, product_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE integration_export_items');
        $this->addSql('DROP TABLE integration_export_plans');
    }
}
