<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds hierarchical, translated catalogue categories and product assignments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE categories (id UUID NOT NULL, tenant_id UUID NOT NULL, parent_id UUID DEFAULT NULL, media_id UUID DEFAULT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, position INT NOT NULL, active BOOLEAN NOT NULL, visible BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_category_tenant_parent_position ON categories (tenant_id, parent_id, position)');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT fk_category_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT fk_category_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE TABLE category_translations (id UUID NOT NULL, category_id UUID NOT NULL, locale_id UUID NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, meta_title VARCHAR(255) DEFAULT NULL, meta_description TEXT DEFAULT NULL, meta_keywords TEXT DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, custom_fields JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_category_translation_locale ON category_translations (category_id, locale_id)');
        $this->addSql('ALTER TABLE category_translations ADD CONSTRAINT fk_category_translation_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE category_translations ADD CONSTRAINT fk_category_translation_locale FOREIGN KEY (locale_id) REFERENCES locales (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE TABLE category_products (category_id UUID NOT NULL, product_id UUID NOT NULL, position INT NOT NULL, PRIMARY KEY(category_id, product_id))');
        $this->addSql('ALTER TABLE category_products ADD CONSTRAINT fk_category_product_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE category_products ADD CONSTRAINT fk_category_product_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE category_products');
        $this->addSql('DROP TABLE category_translations');
        $this->addSql('DROP TABLE categories');
    }
}
