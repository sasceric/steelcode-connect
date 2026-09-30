<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds audited, versioned sales picking tasks without changing inventory balances.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sales_pick_tasks (id UUID NOT NULL, tenant_id UUID NOT NULL, sales_order_id UUID NOT NULL, warehouse_id UUID NOT NULL, created_by_id UUID NOT NULL, updated_by_id UUID DEFAULT NULL, status VARCHAR(24) NOT NULL, lines JSON NOT NULL, picked_quantities JSON NOT NULL, version INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_sales_pick_task_order_created ON sales_pick_tasks (sales_order_id, created_at)');
        $this->addSql('CREATE INDEX idx_sales_pick_task_tenant ON sales_pick_tasks (tenant_id)');
        $this->addSql('CREATE INDEX idx_sales_pick_task_warehouse ON sales_pick_tasks (warehouse_id)');
        $this->addSql('CREATE INDEX idx_sales_pick_task_created_by ON sales_pick_tasks (created_by_id)');
        $this->addSql('CREATE INDEX idx_sales_pick_task_updated_by ON sales_pick_tasks (updated_by_id)');
        $this->addSql('ALTER TABLE sales_pick_tasks ADD CONSTRAINT fk_sales_pick_task_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sales_pick_tasks ADD CONSTRAINT fk_sales_pick_task_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sales_pick_tasks ADD CONSTRAINT fk_sales_pick_task_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE sales_pick_tasks ADD CONSTRAINT fk_sales_pick_task_created_by FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE sales_pick_tasks ADD CONSTRAINT fk_sales_pick_task_updated_by FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sales_pick_tasks');
    }
}
