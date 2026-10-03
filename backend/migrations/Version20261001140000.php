<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bounded tenant-scoped catalogue reconciliation and publication baselines.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE integration_export_plans ADD sync_mode VARCHAR(16) DEFAULT NULL");
        $this->addSql('ALTER TABLE integration_export_plans ADD product_ids JSON DEFAULT NULL');
        $this->addSql(<<<'SQL'
CREATE TABLE integration_catalogue_sync_state (
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    connection_id UUID PRIMARY KEY REFERENCES integration_connections(id) ON DELETE CASCADE,
    settings_hash VARCHAR(64) NOT NULL,
    cursor_id UUID DEFAULT NULL,
    scanned_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
)
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE integration_catalogue_dirty (
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    connection_id UUID NOT NULL REFERENCES integration_connections(id) ON DELETE CASCADE,
    product_id UUID NOT NULL,
    revision UUID NOT NULL,
    PRIMARY KEY (tenant_id, connection_id, product_id)
)
SQL);
        $this->addSql(<<<'SQL'
CREATE TABLE integration_catalogue_publications (
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    connection_id UUID NOT NULL REFERENCES integration_connections(id) ON DELETE CASCADE,
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    settings_hash VARCHAR(64) NOT NULL,
    source_hash VARCHAR(64) NOT NULL,
    target_hash VARCHAR(64) DEFAULT NULL,
    owned_payload JSON NOT NULL,
    published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    attempted_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    attempt_hash VARCHAR(129) NOT NULL,
    PRIMARY KEY (tenant_id, connection_id, product_id)
)
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE integration_catalogue_publications');
        $this->addSql('DROP TABLE integration_catalogue_dirty');
        $this->addSql('DROP TABLE integration_catalogue_sync_state');
        $this->addSql('ALTER TABLE integration_export_plans DROP sync_mode');
        $this->addSql('ALTER TABLE integration_export_plans DROP product_ids');
    }
}
