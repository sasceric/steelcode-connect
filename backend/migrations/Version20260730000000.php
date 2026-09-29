<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730000000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds immutable inventory movement history.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE inventory_movements (id UUID NOT NULL, tenant_id UUID NOT NULL, warehouse_id UUID NOT NULL, product_id UUID NOT NULL, user_id UUID DEFAULT NULL, type VARCHAR(32) NOT NULL, quantity_delta NUMERIC(19, 4) NOT NULL, quantity_after NUMERIC(19, 4) NOT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_inventory_movements_product_created ON inventory_movements (product_id, created_at)');
        $this->addSql('ALTER TABLE inventory_movements ADD CONSTRAINT fk_inventory_movements_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inventory_movements ADD CONSTRAINT fk_inventory_movements_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inventory_movements ADD CONSTRAINT fk_inventory_movements_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inventory_movements ADD CONSTRAINT fk_inventory_movements_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE inventory_movements'); }
}
