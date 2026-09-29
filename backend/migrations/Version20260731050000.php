<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds auditable warehouse inventory counts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE inventory_counts (id UUID NOT NULL, tenant_id UUID NOT NULL, warehouse_id UUID NOT NULL, status VARCHAR(16) NOT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, posted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_inventory_counts_tenant_status ON inventory_counts (tenant_id, status)');
        $this->addSql('ALTER TABLE inventory_counts ADD CONSTRAINT FK_INVENTORY_COUNTS_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inventory_counts ADD CONSTRAINT FK_INVENTORY_COUNTS_WAREHOUSE FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE TABLE inventory_count_items (id UUID NOT NULL, count_id UUID NOT NULL, product_id UUID NOT NULL, expected_quantity NUMERIC(19, 4) NOT NULL, counted_quantity NUMERIC(19, 4) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE inventory_count_items ADD CONSTRAINT FK_INVENTORY_COUNT_ITEMS_COUNT FOREIGN KEY (count_id) REFERENCES inventory_counts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inventory_count_items ADD CONSTRAINT FK_INVENTORY_COUNT_ITEMS_PRODUCT FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE inventory_count_items');
        $this->addSql('DROP TABLE inventory_counts');
    }
}
