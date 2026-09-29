<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Moves translated catalogue slugs into reusable canonical SEO URL records.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE seo_urls (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                locale_id UUID NOT NULL,
                sales_channel_id UUID DEFAULT NULL,
                entity_type VARCHAR(64) NOT NULL,
                entity_id UUID NOT NULL,
                path VARCHAR(1024) NOT NULL,
                canonical BOOLEAN NOT NULL,
                modified BOOLEAN NOT NULL,
                active BOOLEAN NOT NULL,
                redirect_code INT DEFAULT NULL,
                redirect_target_id UUID DEFAULT NULL,
                source VARCHAR(16) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )',
        );
        $this->addSql('CREATE INDEX idx_seo_url_entity ON seo_urls (tenant_id, entity_type, entity_id)');
        $this->addSql('ALTER TABLE seo_urls ADD CONSTRAINT fk_seo_url_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE seo_urls ADD CONSTRAINT fk_seo_url_locale FOREIGN KEY (locale_id) REFERENCES locales (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE seo_urls ADD CONSTRAINT fk_seo_url_sales_channel FOREIGN KEY (sales_channel_id) REFERENCES integration_sales_channels (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE seo_urls ADD CONSTRAINT fk_seo_url_redirect_target FOREIGN KEY (redirect_target_id) REFERENCES seo_urls (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql(<<<'SQL'
            WITH legacy_urls AS (
                SELECT product.tenant_id, 'product'::varchar AS entity_type, translation.product_id AS entity_id, translation.locale_id, trim(translation.slug) AS path
                FROM product_translations AS translation
                INNER JOIN products AS product ON product.id = translation.product_id
                WHERE translation.slug IS NOT NULL AND trim(translation.slug) <> ''
                UNION ALL
                SELECT category.tenant_id, 'category'::varchar AS entity_type, translation.category_id AS entity_id, translation.locale_id, trim(translation.slug) AS path
                FROM category_translations AS translation
                INNER JOIN categories AS category ON category.id = translation.category_id
                WHERE translation.slug IS NOT NULL AND trim(translation.slug) <> ''
                UNION ALL
                SELECT manufacturer.tenant_id, 'manufacturer'::varchar AS entity_type, translation.manufacturer_id AS entity_id, translation.locale_id, trim(translation.slug) AS path
                FROM manufacturer_translations AS translation
                INNER JOIN manufacturers AS manufacturer ON manufacturer.id = translation.manufacturer_id
                WHERE translation.slug IS NOT NULL AND trim(translation.slug) <> ''
            ), numbered_urls AS (
                SELECT *, ROW_NUMBER() OVER (PARTITION BY tenant_id, locale_id, path ORDER BY entity_type, entity_id) AS collision_number
                FROM legacy_urls
            )
            INSERT INTO seo_urls (
                id, tenant_id, locale_id, entity_type, entity_id, path,
                canonical, modified, active, source, created_at, updated_at
            )
            SELECT
                md5(random()::text || clock_timestamp()::text || entity_type || entity_id::text || locale_id::text)::uuid,
                tenant_id,
                locale_id,
                entity_type,
                entity_id,
                CASE
                    WHEN collision_number = 1 THEN path
                    ELSE path || '-' || entity_type || '-' || collision_number
                END,
                TRUE,
                FALSE,
                TRUE,
                'generated',
                NOW(),
                NOW()
            FROM numbered_urls
            SQL,
        );
        $this->addSql("CREATE UNIQUE INDEX uniq_seo_url_active_path ON seo_urls (tenant_id, locale_id, COALESCE(sales_channel_id, '00000000-0000-0000-0000-000000000000'::uuid), path) WHERE active");
        $this->addSql("CREATE UNIQUE INDEX uniq_seo_url_canonical ON seo_urls (tenant_id, entity_type, entity_id, locale_id, COALESCE(sales_channel_id, '00000000-0000-0000-0000-000000000000'::uuid)) WHERE canonical AND active");
        $this->addSql('ALTER TABLE product_translations DROP slug');
        $this->addSql('ALTER TABLE category_translations DROP slug');
        $this->addSql('ALTER TABLE manufacturer_translations DROP slug');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_translations ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE category_translations ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE manufacturer_translations ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE product_translations AS translation
            SET slug = seo_url.path
            FROM seo_urls AS seo_url
            WHERE seo_url.entity_type = 'product'
              AND seo_url.entity_id = translation.product_id
              AND seo_url.locale_id = translation.locale_id
              AND seo_url.sales_channel_id IS NULL
              AND seo_url.canonical = TRUE
              AND seo_url.active = TRUE
            SQL,
        );
        $this->addSql(<<<'SQL'
            UPDATE category_translations AS translation
            SET slug = seo_url.path
            FROM seo_urls AS seo_url
            WHERE seo_url.entity_type = 'category'
              AND seo_url.entity_id = translation.category_id
              AND seo_url.locale_id = translation.locale_id
              AND seo_url.sales_channel_id IS NULL
              AND seo_url.canonical = TRUE
              AND seo_url.active = TRUE
            SQL,
        );
        $this->addSql(<<<'SQL'
            UPDATE manufacturer_translations AS translation
            SET slug = seo_url.path
            FROM seo_urls AS seo_url
            WHERE seo_url.entity_type = 'manufacturer'
              AND seo_url.entity_id = translation.manufacturer_id
              AND seo_url.locale_id = translation.locale_id
              AND seo_url.sales_channel_id IS NULL
              AND seo_url.canonical = TRUE
              AND seo_url.active = TRUE
            SQL,
        );
        $this->addSql('DROP TABLE seo_urls');
    }
}
