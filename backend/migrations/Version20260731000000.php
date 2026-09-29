<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds product-download and cross-selling catalogue relations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE product_downloads (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                product_id UUID NOT NULL,
                media_id UUID NOT NULL,
                title VARCHAR(255) DEFAULT NULL,
                position INT NOT NULL,
                PRIMARY KEY(id)
            )',
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_product_download_media ON product_downloads (tenant_id, product_id, media_id)');
        $this->addSql('ALTER TABLE product_downloads ADD CONSTRAINT fk_product_download_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_downloads ADD CONSTRAINT fk_product_download_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_downloads ADD CONSTRAINT fk_product_download_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(
            'CREATE TABLE product_cross_sellings (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                product_id UUID NOT NULL,
                source_id VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(32) NOT NULL,
                active BOOLEAN NOT NULL,
                position INT NOT NULL,
                source_product_stream_id VARCHAR(64) DEFAULT NULL,
                PRIMARY KEY(id)
            )',
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_product_cross_selling_source ON product_cross_sellings (product_id, source_id)');
        $this->addSql('ALTER TABLE product_cross_sellings ADD CONSTRAINT fk_product_cross_selling_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_cross_sellings ADD CONSTRAINT fk_product_cross_selling_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(
            'CREATE TABLE product_cross_selling_assignments (
                id UUID NOT NULL,
                cross_selling_id UUID NOT NULL,
                assigned_product_id UUID NOT NULL,
                position INT NOT NULL,
                PRIMARY KEY(id)
            )',
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_cross_selling_assigned_product ON product_cross_selling_assignments (cross_selling_id, assigned_product_id)');
        $this->addSql('ALTER TABLE product_cross_selling_assignments ADD CONSTRAINT fk_cross_selling_assignment_group FOREIGN KEY (cross_selling_id) REFERENCES product_cross_sellings (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_cross_selling_assignments ADD CONSTRAINT fk_cross_selling_assignment_product FOREIGN KEY (assigned_product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(
            'CREATE TABLE product_cross_selling_translations (
                id UUID NOT NULL,
                cross_selling_id UUID NOT NULL,
                locale_id UUID NOT NULL,
                name VARCHAR(255) NOT NULL,
                PRIMARY KEY(id)
            )',
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_product_cross_selling_translation_locale ON product_cross_selling_translations (cross_selling_id, locale_id)');
        $this->addSql('ALTER TABLE product_cross_selling_translations ADD CONSTRAINT fk_cross_selling_translation_group FOREIGN KEY (cross_selling_id) REFERENCES product_cross_sellings (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_cross_selling_translations ADD CONSTRAINT fk_cross_selling_translation_locale FOREIGN KEY (locale_id) REFERENCES locales (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product_cross_selling_translations');
        $this->addSql('DROP TABLE product_cross_selling_assignments');
        $this->addSql('DROP TABLE product_cross_sellings');
        $this->addSql('DROP TABLE product_downloads');
    }
}
