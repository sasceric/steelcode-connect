<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds supplier offers, purchase orders, lines, and audited receipts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE supplier_offers (id UUID NOT NULL, tenant_id UUID NOT NULL, supplier_id UUID NOT NULL, product_id UUID NOT NULL, supplier_sku VARCHAR(128) DEFAULT NULL, unit_cost NUMERIC(19, 4) NOT NULL, currency VARCHAR(3) NOT NULL, minimum_quantity NUMERIC(19, 4) NOT NULL, lead_time_days INT DEFAULT NULL, preferred BOOLEAN NOT NULL, active BOOLEAN NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_supplier_offer_product ON supplier_offers (tenant_id, supplier_id, product_id)');
        $this->addSql('CREATE INDEX idx_supplier_offers_supplier ON supplier_offers (supplier_id)');
        $this->addSql('CREATE TABLE purchase_orders (id UUID NOT NULL, tenant_id UUID NOT NULL, supplier_id UUID NOT NULL, warehouse_id UUID NOT NULL, status VARCHAR(24) NOT NULL, currency VARCHAR(3) NOT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_purchase_orders_tenant_created ON purchase_orders (tenant_id, created_at)');
        $this->addSql('CREATE INDEX idx_purchase_orders_supplier ON purchase_orders (supplier_id)');
        $this->addSql('CREATE INDEX idx_purchase_orders_warehouse ON purchase_orders (warehouse_id)');
        $this->addSql('CREATE TABLE purchase_order_items (id UUID NOT NULL, purchase_order_id UUID NOT NULL, product_id UUID NOT NULL, quantity NUMERIC(19, 4) NOT NULL, received_quantity NUMERIC(19, 4) NOT NULL, damaged_quantity NUMERIC(19, 4) NOT NULL, unit_cost NUMERIC(19, 4) NOT NULL, supplier_sku VARCHAR(128) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_purchase_order_product ON purchase_order_items (purchase_order_id, product_id)');
        $this->addSql('CREATE INDEX idx_purchase_order_items_product ON purchase_order_items (product_id)');
        $this->addSql('CREATE TABLE purchase_receipts (id UUID NOT NULL, purchase_order_id UUID NOT NULL, item_id UUID NOT NULL, user_id UUID DEFAULT NULL, good_quantity NUMERIC(19, 4) NOT NULL, damaged_quantity NUMERIC(19, 4) NOT NULL, note TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_purchase_receipts_order_created ON purchase_receipts (purchase_order_id, created_at)');
        $this->addSql('CREATE INDEX idx_purchase_receipts_item ON purchase_receipts (item_id)');
        $this->addSql('ALTER TABLE supplier_offers ADD CONSTRAINT FK_OFFERS_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supplier_offers ADD CONSTRAINT FK_OFFERS_SUPPLIER FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supplier_offers ADD CONSTRAINT FK_OFFERS_PRODUCT FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE purchase_orders ADD CONSTRAINT FK_PO_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE purchase_orders ADD CONSTRAINT FK_PO_SUPPLIER FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE purchase_orders ADD CONSTRAINT FK_PO_WAREHOUSE FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE purchase_order_items ADD CONSTRAINT FK_PO_ITEMS_ORDER FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE purchase_order_items ADD CONSTRAINT FK_PO_ITEMS_PRODUCT FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE purchase_receipts ADD CONSTRAINT FK_RECEIPTS_ORDER FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE purchase_receipts ADD CONSTRAINT FK_RECEIPTS_ITEM FOREIGN KEY (item_id) REFERENCES purchase_order_items (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE purchase_receipts ADD CONSTRAINT FK_RECEIPTS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE purchase_receipts');
        $this->addSql('DROP TABLE purchase_order_items');
        $this->addSql('DROP TABLE purchase_orders');
        $this->addSql('DROP TABLE supplier_offers');
    }
}
