<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a durable, coalesced stock-sync outbox for channel publications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE inventory_sync_outbox (id UUID NOT NULL, tenant_id UUID NOT NULL, product_id UUID NOT NULL, status VARCHAR(24) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_inventory_sync_outbox_product ON inventory_sync_outbox (tenant_id, product_id)');
        $this->addSql('CREATE INDEX idx_inventory_sync_outbox_status_updated ON inventory_sync_outbox (status, updated_at)');
        $this->addSql('ALTER TABLE inventory_sync_outbox ADD CONSTRAINT FK_INVENTORY_SYNC_OUTBOX_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_sync_outbox ADD CONSTRAINT FK_INVENTORY_SYNC_OUTBOX_PRODUCT FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE inventory_sync_outbox');
    }
}
