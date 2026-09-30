<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Separates supplier price tiers from supplier-product purchasing terms and permits fractional purchase-unit conversions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE supplier_offers ADD minimum_order_quantity NUMERIC(19, 4) NOT NULL DEFAULT 1');
        $this->addSql('UPDATE supplier_offers SET minimum_order_quantity = minimum_quantity');
        $this->addSql('ALTER TABLE supplier_offers ALTER COLUMN stock_units_per_purchase_unit TYPE NUMERIC(19, 4)');
        $this->addSql('ALTER TABLE purchase_order_items ALTER COLUMN stock_units_per_purchase_unit TYPE NUMERIC(19, 4)');
        $this->addSql('CREATE TABLE supplier_offer_prices (id UUID NOT NULL, supplier_offer_id UUID NOT NULL, minimum_quantity NUMERIC(19, 4) NOT NULL, unit_cost NUMERIC(19, 4) NOT NULL, currency VARCHAR(3) NOT NULL, valid_from DATE DEFAULT NULL, valid_until DATE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_supplier_offer_prices_lookup ON supplier_offer_prices (supplier_offer_id, currency, minimum_quantity)');
        $this->addSql('ALTER TABLE supplier_offer_prices ADD CONSTRAINT FK_SUPPLIER_OFFER_PRICES_OFFER FOREIGN KEY (supplier_offer_id) REFERENCES supplier_offers (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supplier_offer_prices ADD CONSTRAINT chk_supplier_offer_prices CHECK (minimum_quantity > 0 AND unit_cost >= 0 AND (valid_from IS NULL OR valid_until IS NULL OR valid_from <= valid_until))');
        $this->addSql('ALTER TABLE supplier_offers ADD CONSTRAINT chk_supplier_offer_terms CHECK (minimum_order_quantity > 0 AND stock_units_per_purchase_unit > 0)');
        $this->addSql("INSERT INTO supplier_offer_prices (id, supplier_offer_id, minimum_quantity, unit_cost, currency, valid_from, valid_until) SELECT id, id, 1, unit_cost, currency, valid_from, valid_until FROM supplier_offers WHERE unit_cost > 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE supplier_offer_prices');
        $this->addSql('ALTER TABLE supplier_offers DROP CONSTRAINT chk_supplier_offer_terms');
        $this->addSql('ALTER TABLE purchase_order_items ALTER COLUMN stock_units_per_purchase_unit TYPE INT');
        $this->addSql('ALTER TABLE supplier_offers ALTER COLUMN stock_units_per_purchase_unit TYPE INT');
        $this->addSql('ALTER TABLE supplier_offers DROP COLUMN minimum_order_quantity');
    }
}
