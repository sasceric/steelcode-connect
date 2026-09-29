<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds supplier contact/address, purchase-unit snapshots and damaged receipt resolution.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE suppliers ADD contact_name VARCHAR(255) DEFAULT NULL, ADD street VARCHAR(255) DEFAULT NULL, ADD postal_code VARCHAR(32) DEFAULT NULL, ADD city VARCHAR(128) DEFAULT NULL, ADD country VARCHAR(128) DEFAULT NULL');
        $this->addSql("ALTER TABLE supplier_offers ADD purchase_unit VARCHAR(64) NOT NULL DEFAULT 'unit', ADD stock_units_per_purchase_unit INT NOT NULL DEFAULT 1");
        $this->addSql("ALTER TABLE purchase_order_items ADD purchase_unit VARCHAR(64) NOT NULL DEFAULT 'unit', ADD stock_units_per_purchase_unit INT NOT NULL DEFAULT 1");
        $this->addSql('ALTER TABLE purchase_orders ADD supplier_snapshot JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE purchase_receipts ADD damage_resolution VARCHAR(24) DEFAULT NULL, ADD damage_resolution_note TEXT DEFAULT NULL, ADD damage_resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("UPDATE purchase_receipts SET damage_resolution = 'open' WHERE damaged_quantity > 0");
        $this->addSql('ALTER TABLE supplier_offers ADD CONSTRAINT chk_supplier_offer_unit_factor CHECK (stock_units_per_purchase_unit > 0)');
        $this->addSql('ALTER TABLE purchase_order_items ADD CONSTRAINT chk_purchase_order_item_unit_factor CHECK (stock_units_per_purchase_unit > 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE purchase_order_items DROP CONSTRAINT chk_purchase_order_item_unit_factor');
        $this->addSql('ALTER TABLE supplier_offers DROP CONSTRAINT chk_supplier_offer_unit_factor');
        $this->addSql('ALTER TABLE purchase_receipts DROP damage_resolution, DROP damage_resolution_note, DROP damage_resolved_at');
        $this->addSql('ALTER TABLE purchase_orders DROP supplier_snapshot');
        $this->addSql('ALTER TABLE purchase_order_items DROP purchase_unit, DROP stock_units_per_purchase_unit');
        $this->addSql('ALTER TABLE supplier_offers DROP purchase_unit, DROP stock_units_per_purchase_unit');
        $this->addSql('ALTER TABLE suppliers DROP contact_name, DROP street, DROP postal_code, DROP city, DROP country');
    }
}
