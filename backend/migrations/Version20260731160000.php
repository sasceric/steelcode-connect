<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds supplier invoices and line-level three-way matching records.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE supplier_invoices (id UUID NOT NULL, tenant_id UUID NOT NULL, supplier_id UUID NOT NULL, purchase_order_id UUID NOT NULL, invoice_number VARCHAR(100) NOT NULL, invoice_year INT NOT NULL, invoice_date DATE NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(24) NOT NULL, tax NUMERIC(19, 4) NOT NULL, shipping NUMERIC(19, 4) NOT NULL, discount NUMERIC(19, 4) NOT NULL, declared_total NUMERIC(19, 4) NOT NULL, note TEXT DEFAULT NULL, match_issues JSON NOT NULL, matched_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, voided_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, void_reason TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_supplier_invoice_number ON supplier_invoices (tenant_id, supplier_id, invoice_year, invoice_number)');
        $this->addSql('CREATE INDEX idx_supplier_invoices_tenant_created ON supplier_invoices (tenant_id, created_at)');
        $this->addSql('CREATE INDEX IDX_SUPPLIER_INVOICES_SUPPLIER ON supplier_invoices (supplier_id)');
        $this->addSql('CREATE INDEX IDX_SUPPLIER_INVOICES_PO ON supplier_invoices (purchase_order_id)');
        $this->addSql('ALTER TABLE supplier_invoices ADD CONSTRAINT FK_SUPPLIER_INVOICES_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supplier_invoices ADD CONSTRAINT FK_SUPPLIER_INVOICES_SUPPLIER FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE supplier_invoices ADD CONSTRAINT FK_SUPPLIER_INVOICES_PO FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE RESTRICT');
        $this->addSql('CREATE TABLE supplier_invoice_items (id UUID NOT NULL, invoice_id UUID NOT NULL, purchase_order_item_id UUID NOT NULL, quantity NUMERIC(19, 4) NOT NULL, unit_cost NUMERIC(19, 4) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_supplier_invoice_order_item ON supplier_invoice_items (invoice_id, purchase_order_item_id)');
        $this->addSql('CREATE INDEX IDX_SUPPLIER_INVOICE_ITEMS_PO_ITEM ON supplier_invoice_items (purchase_order_item_id)');
        $this->addSql('ALTER TABLE supplier_invoice_items ADD CONSTRAINT FK_SUPPLIER_INVOICE_ITEMS_INVOICE FOREIGN KEY (invoice_id) REFERENCES supplier_invoices (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supplier_invoice_items ADD CONSTRAINT FK_SUPPLIER_INVOICE_ITEMS_PO_ITEM FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE supplier_invoices ADD CONSTRAINT chk_supplier_invoice_amounts CHECK (tax >= 0 AND shipping >= 0 AND discount >= 0 AND declared_total >= 0)');
        $this->addSql('ALTER TABLE supplier_invoice_items ADD CONSTRAINT chk_supplier_invoice_item_amounts CHECK (quantity > 0 AND unit_cost >= 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE supplier_invoice_items');
        $this->addSql('DROP TABLE supplier_invoices');
    }
}
