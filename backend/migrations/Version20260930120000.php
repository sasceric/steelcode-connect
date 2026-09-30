<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Records quantity-limited, order-linked customer returns and their stock dispositions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sales_returns (id UUID NOT NULL, tenant_id UUID NOT NULL, sales_order_id UUID NOT NULL, sales_order_item_id UUID NOT NULL, warehouse_id UUID NOT NULL, request_id UUID NOT NULL, received_by_id UUID NOT NULL, resolved_by_id UUID DEFAULT NULL, quantity NUMERIC(19, 4) NOT NULL, reason VARCHAR(64) NOT NULL, condition VARCHAR(24) NOT NULL, received_condition VARCHAR(24) NOT NULL, disposition VARCHAR(24) NOT NULL, initial_disposition VARCHAR(24) NOT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_sales_return_request ON sales_returns (tenant_id, request_id)');
        $this->addSql('CREATE INDEX idx_sales_return_order ON sales_returns (sales_order_id, created_at)');
        $this->addSql('CREATE INDEX idx_sales_return_item ON sales_returns (sales_order_item_id)');
        $this->addSql('CREATE INDEX idx_sales_return_warehouse ON sales_returns (warehouse_id)');
        $this->addSql('CREATE INDEX idx_sales_return_received_by ON sales_returns (received_by_id)');
        $this->addSql('CREATE INDEX idx_sales_return_resolved_by ON sales_returns (resolved_by_id)');
        $this->addSql('ALTER TABLE sales_returns ADD CONSTRAINT fk_sales_return_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sales_returns ADD CONSTRAINT fk_sales_return_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sales_returns ADD CONSTRAINT fk_sales_return_item FOREIGN KEY (sales_order_item_id) REFERENCES sales_order_items (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE sales_returns ADD CONSTRAINT fk_sales_return_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE sales_returns ADD CONSTRAINT fk_sales_return_received_by FOREIGN KEY (received_by_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE sales_returns ADD CONSTRAINT fk_sales_return_resolved_by FOREIGN KEY (resolved_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sales_returns');
    }
}
