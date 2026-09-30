<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds connector-originated sales orders, line snapshots, and warehouse allocation records.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE sales_orders (id UUID NOT NULL, tenant_id UUID NOT NULL, connection_id UUID NOT NULL, external_id VARCHAR(128) NOT NULL, external_number VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, source_payload JSON NOT NULL DEFAULT '{}'::json, ordered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_sales_order_connection_external ON sales_orders (connection_id, external_id)');
        $this->addSql('CREATE INDEX idx_sales_order_tenant_status_created ON sales_orders (tenant_id, status, created_at)');
        $this->addSql('ALTER TABLE sales_orders ADD CONSTRAINT FK_SALES_ORDER_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sales_orders ADD CONSTRAINT FK_SALES_ORDER_CONNECTION FOREIGN KEY (connection_id) REFERENCES integration_connections (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE sales_order_items (id UUID NOT NULL, sales_order_id UUID NOT NULL, product_id UUID NOT NULL, external_line_id VARCHAR(128) NOT NULL, sku VARCHAR(255) NOT NULL, name VARCHAR(512) NOT NULL, quantity NUMERIC(19, 4) NOT NULL, reserved_quantity NUMERIC(19, 4) NOT NULL DEFAULT 0, fulfilled_quantity NUMERIC(19, 4) NOT NULL DEFAULT 0, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_sales_order_external_line ON sales_order_items (sales_order_id, external_line_id)');
        $this->addSql('ALTER TABLE sales_order_items ADD CONSTRAINT FK_SALES_ORDER_ITEM_ORDER FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sales_order_items ADD CONSTRAINT FK_SALES_ORDER_ITEM_PRODUCT FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT');

        $this->addSql('CREATE TABLE sales_order_allocations (id UUID NOT NULL, sales_order_item_id UUID NOT NULL, warehouse_id UUID NOT NULL, quantity NUMERIC(19, 4) NOT NULL, status VARCHAR(24) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_sales_order_allocation_warehouse_status ON sales_order_allocations (warehouse_id, status)');
        $this->addSql('ALTER TABLE sales_order_allocations ADD CONSTRAINT FK_SALES_ORDER_ALLOCATION_ITEM FOREIGN KEY (sales_order_item_id) REFERENCES sales_order_items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sales_order_allocations ADD CONSTRAINT FK_SALES_ORDER_ALLOCATION_WAREHOUSE FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sales_order_allocations');
        $this->addSql('DROP TABLE sales_order_items');
        $this->addSql('DROP TABLE sales_orders');
    }
}
