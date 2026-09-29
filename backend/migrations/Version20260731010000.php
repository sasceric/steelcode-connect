<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds indexes for paged catalogue lists, assignments, and case-insensitive search.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        $this->addSql('CREATE INDEX idx_product_tenant_parent_updated ON products (tenant_id, parent_id, updated_at)');
        $this->addSql('CREATE INDEX idx_product_tenant_parent_sku ON products (tenant_id, parent_id, sku)');
        $this->addSql('CREATE INDEX idx_product_tenant_manufacturer_parent ON products (tenant_id, manufacturer_id, parent_id)');
        $this->addSql('CREATE INDEX idx_product_parent_created ON products (parent_id, created_at)');
        $this->addSql('CREATE INDEX idx_product_media_product_position ON product_media (product_id, sort_order)');

        $this->addSql('CREATE INDEX idx_products_sku_trgm ON products USING GIN (LOWER(sku) gin_trgm_ops)');
        $this->addSql('CREATE INDEX idx_product_translations_name_trgm ON product_translations USING GIN (LOWER(name) gin_trgm_ops)');
        $this->addSql('CREATE INDEX idx_manufacturer_translations_name_trgm ON manufacturer_translations USING GIN (LOWER(name) gin_trgm_ops)');
        $this->addSql('CREATE INDEX idx_property_group_translations_name_trgm ON property_group_translations USING GIN (LOWER(name) gin_trgm_ops)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_property_group_translations_name_trgm');
        $this->addSql('DROP INDEX idx_manufacturer_translations_name_trgm');
        $this->addSql('DROP INDEX idx_product_translations_name_trgm');
        $this->addSql('DROP INDEX idx_products_sku_trgm');
        $this->addSql('DROP INDEX idx_product_media_product_position');
        $this->addSql('DROP INDEX idx_product_parent_created');
        $this->addSql('DROP INDEX idx_product_tenant_manufacturer_parent');
        $this->addSql('DROP INDEX idx_product_tenant_parent_sku');
        $this->addSql('DROP INDEX idx_product_tenant_parent_updated');
    }
}
