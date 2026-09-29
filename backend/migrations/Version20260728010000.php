<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the canonical parent/child product catalog foundation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE currencies (id UUID NOT NULL, code VARCHAR(3) NOT NULL, symbol VARCHAR(32) NOT NULL, decimal_precision INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_currency_code ON currencies (code)');

        $this->addSql('CREATE TABLE tenant_currencies (id UUID NOT NULL, tenant_id UUID NOT NULL, currency_id UUID NOT NULL, enabled BOOLEAN NOT NULL, is_default BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_tenant_currency ON tenant_currencies (tenant_id, currency_id)');
        $this->addSql('CREATE INDEX idx_tenant_currencies_tenant ON tenant_currencies (tenant_id)');
        $this->addSql('CREATE INDEX idx_tenant_currencies_currency ON tenant_currencies (currency_id)');

        $this->addSql('CREATE TABLE manufacturers (id UUID NOT NULL, tenant_id UUID NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, website VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_manufacturer_tenant_slug ON manufacturers (tenant_id, slug)');
        $this->addSql('CREATE TABLE products (id UUID NOT NULL, tenant_id UUID NOT NULL, parent_id UUID DEFAULT NULL, manufacturer_id UUID DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, sku VARCHAR(255) DEFAULT NULL, ean VARCHAR(64) DEFAULT NULL, option_values JSON NOT NULL, short_description TEXT DEFAULT NULL, description TEXT DEFAULT NULL, weight_grams INT DEFAULT NULL, length_millimeters INT DEFAULT NULL, width_millimeters INT DEFAULT NULL, height_millimeters INT DEFAULT NULL, status VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_tenant_slug ON products (tenant_id, slug)');
        $this->addSql('CREATE INDEX idx_product_tenant_status_updated ON products (tenant_id, status, updated_at)');

        $this->addSql('CREATE UNIQUE INDEX uniq_product_tenant_sku ON products (tenant_id, sku)');
        $this->addSql('CREATE INDEX idx_products_parent ON products (parent_id)');

        $this->addSql('CREATE TABLE product_prices (id UUID NOT NULL, tenant_id UUID NOT NULL, product_id UUID NOT NULL, currency_id UUID NOT NULL, price_type VARCHAR(16) NOT NULL, net_amount INT NOT NULL, gross_amount INT NOT NULL, tax_rate NUMERIC(5, 2) NOT NULL, quantity_start NUMERIC(19, 4) NOT NULL, quantity_end NUMERIC(19, 4) DEFAULT NULL, pricing_context VARCHAR(64) NOT NULL DEFAULT \'default\', list_net_amount INT DEFAULT NULL, list_gross_amount INT DEFAULT NULL, valid_from TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, valid_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, source VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_price_tier ON product_prices (tenant_id, product_id, currency_id, price_type, pricing_context, quantity_start)');
        $this->addSql('CREATE INDEX idx_product_prices_product ON product_prices (product_id)');
        $this->addSql('CREATE INDEX idx_product_prices_currency ON product_prices (currency_id)');

        $this->addSql('CREATE TABLE warehouses (id UUID NOT NULL, tenant_id UUID NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_warehouse_tenant_code ON warehouses (tenant_id, code)');

        $this->addSql('CREATE TABLE inventory_levels (id UUID NOT NULL, tenant_id UUID NOT NULL, warehouse_id UUID NOT NULL, product_id UUID NOT NULL, quantity NUMERIC(19, 4) NOT NULL, reserved_quantity NUMERIC(19, 4) NOT NULL, reorder_threshold NUMERIC(19, 4) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_inventory_level ON inventory_levels (tenant_id, warehouse_id, product_id)');
        $this->addSql('CREATE INDEX idx_inventory_levels_warehouse ON inventory_levels (warehouse_id)');
        $this->addSql('CREATE INDEX idx_inventory_levels_product ON inventory_levels (product_id)');

        $this->addSql('CREATE TABLE product_media (id UUID NOT NULL, tenant_id UUID NOT NULL, product_id UUID NOT NULL, storage_key VARCHAR(512) NOT NULL, original_url VARCHAR(2048) DEFAULT NULL, media_type VARCHAR(32) NOT NULL, alt_text VARCHAR(255) DEFAULT NULL, sort_order INT NOT NULL, checksum VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_media_position ON product_media (tenant_id, product_id, sort_order)');
        $this->addSql('CREATE TABLE product_properties (id UUID NOT NULL, tenant_id UUID NOT NULL, product_id UUID NOT NULL, property_key VARCHAR(100) NOT NULL, label VARCHAR(255) NOT NULL, value JSON NOT NULL, unit VARCHAR(64) DEFAULT NULL, source VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_property_key ON product_properties (tenant_id, product_id, property_key)');
        $this->addSql('CREATE TABLE product_option_groups (id UUID NOT NULL, tenant_id UUID NOT NULL, product_id UUID NOT NULL, name VARCHAR(255) NOT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_option_group_name ON product_option_groups (product_id, name)');
        $this->addSql('CREATE TABLE product_option_values (id UUID NOT NULL, tenant_id UUID NOT NULL, option_group_id UUID NOT NULL, value VARCHAR(255) NOT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_option_value ON product_option_values (option_group_id, value)');

        $this->addSql('ALTER TABLE tenant_currencies ADD CONSTRAINT fk_tenant_currencies_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tenant_currencies ADD CONSTRAINT fk_tenant_currencies_currency FOREIGN KEY (currency_id) REFERENCES currencies (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE manufacturers ADD CONSTRAINT fk_manufacturers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT fk_products_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT fk_products_parent FOREIGN KEY (parent_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT fk_products_manufacturer FOREIGN KEY (manufacturer_id) REFERENCES manufacturers (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_prices ADD CONSTRAINT fk_product_prices_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_prices ADD CONSTRAINT fk_product_prices_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_prices ADD CONSTRAINT fk_product_prices_currency FOREIGN KEY (currency_id) REFERENCES currencies (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE warehouses ADD CONSTRAINT fk_warehouses_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inventory_levels ADD CONSTRAINT fk_inventory_levels_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inventory_levels ADD CONSTRAINT fk_inventory_levels_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE inventory_levels ADD CONSTRAINT fk_inventory_levels_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_media ADD CONSTRAINT fk_product_media_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_properties ADD CONSTRAINT fk_product_properties_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_option_groups ADD CONSTRAINT fk_product_option_groups_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_option_values ADD CONSTRAINT fk_product_option_values_group FOREIGN KEY (option_group_id) REFERENCES product_option_groups (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE inventory_levels');
        $this->addSql('DROP TABLE product_option_values');
        $this->addSql('DROP TABLE product_option_groups');
        $this->addSql('DROP TABLE product_properties');
        $this->addSql('DROP TABLE product_media');
        $this->addSql('DROP TABLE warehouses');
        $this->addSql('DROP TABLE product_prices');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE manufacturers');
        $this->addSql('DROP TABLE tenant_currencies');
        $this->addSql('DROP TABLE currencies');
    }
}
