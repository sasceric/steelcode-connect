<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds internal warehouse transfers and transfer line items.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE inventory_transfers (id UUID NOT NULL, tenant_id UUID NOT NULL, source_warehouse_id UUID NOT NULL, destination_warehouse_id UUID NOT NULL, status VARCHAR(16) NOT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_INVENTORY_TRANSFERS_TENANT ON inventory_transfers (tenant_id)');
        $this->addSql('CREATE INDEX IDX_INVENTORY_TRANSFERS_SOURCE ON inventory_transfers (source_warehouse_id)');
        $this->addSql('CREATE INDEX IDX_INVENTORY_TRANSFERS_DESTINATION ON inventory_transfers (destination_warehouse_id)');
        $this->addSql('CREATE INDEX idx_inventory_transfers_tenant_status ON inventory_transfers (tenant_id, status)');
        $this->addSql('ALTER TABLE inventory_transfers ADD CONSTRAINT FK_INVENTORY_TRANSFERS_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inventory_transfers ADD CONSTRAINT FK_INVENTORY_TRANSFERS_SOURCE FOREIGN KEY (source_warehouse_id) REFERENCES warehouses (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inventory_transfers ADD CONSTRAINT FK_INVENTORY_TRANSFERS_DESTINATION FOREIGN KEY (destination_warehouse_id) REFERENCES warehouses (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE TABLE inventory_transfer_items (id UUID NOT NULL, transfer_id UUID NOT NULL, product_id UUID NOT NULL, quantity NUMERIC(19, 4) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_INVENTORY_TRANSFER_ITEMS_TRANSFER ON inventory_transfer_items (transfer_id)');
        $this->addSql('CREATE INDEX IDX_INVENTORY_TRANSFER_ITEMS_PRODUCT ON inventory_transfer_items (product_id)');
        $this->addSql('ALTER TABLE inventory_transfer_items ADD CONSTRAINT FK_INVENTORY_TRANSFER_ITEMS_TRANSFER FOREIGN KEY (transfer_id) REFERENCES inventory_transfers (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inventory_transfer_items ADD CONSTRAINT FK_INVENTORY_TRANSFER_ITEMS_PRODUCT FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE inventory_transfer_items');
        $this->addSql('DROP TABLE inventory_transfers');
    }
}
