<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds tenant-scoped brands, catalogue translations and source-owned product brand assignments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE brands (id UUID NOT NULL, tenant_id UUID NOT NULL, slug VARCHAR(255) DEFAULT NULL, source_payload JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_brand_tenant ON brands (tenant_id)');
        $this->addSql('ALTER TABLE brands ADD CONSTRAINT fk_brand_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('CREATE TABLE brand_translations (id UUID NOT NULL, brand_id UUID NOT NULL, locale_id UUID NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_brand_translation_locale ON brand_translations (brand_id, locale_id)');
        $this->addSql('CREATE INDEX idx_brand_translation_locale ON brand_translations (locale_id)');
        $this->addSql('ALTER TABLE brand_translations ADD CONSTRAINT fk_brand_translation_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE brand_translations ADD CONSTRAINT fk_brand_translation_locale FOREIGN KEY (locale_id) REFERENCES locales (id) ON DELETE RESTRICT');
        $this->addSql('CREATE TABLE product_brands (id UUID NOT NULL, tenant_id UUID NOT NULL, product_id UUID NOT NULL, brand_id UUID NOT NULL, source_key VARCHAR(64) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_brand_source ON product_brands (tenant_id, product_id, brand_id, source_key)');
        $this->addSql('CREATE INDEX idx_product_brand_lookup ON product_brands (tenant_id, product_id)');
        $this->addSql('CREATE INDEX idx_product_brand_product ON product_brands (product_id)');
        $this->addSql('CREATE INDEX idx_product_brand_brand ON product_brands (brand_id)');
        $this->addSql('ALTER TABLE product_brands ADD CONSTRAINT fk_product_brand_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_brands ADD CONSTRAINT fk_product_brand_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_brands ADD CONSTRAINT fk_product_brand_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product_brands');
        $this->addSql('DROP TABLE brand_translations');
        $this->addSql('DROP TABLE brands');
    }
}
