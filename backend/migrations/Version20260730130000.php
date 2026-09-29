<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Moves canonical product content from products into product translations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO product_translations (
                id,
                product_id,
                locale_id,
                name,
                short_description,
                description,
                meta_title,
                meta_description,
                slug,
                custom_fields
            )
            SELECT
                md5(random()::text || clock_timestamp()::text)::uuid,
                product.id,
                locale.id,
                product.name,
                product.short_description,
                product.description,
                NULL,
                NULL,
                product.slug,
                '{}'::json
            FROM products AS product
            INNER JOIN tenants AS tenant ON tenant.id = product.tenant_id
            INNER JOIN locales AS locale ON locale.code = tenant.default_snippet_locale
            ON CONFLICT (product_id, locale_id) DO UPDATE SET
                name = EXCLUDED.name,
                short_description = COALESCE(product_translations.short_description, EXCLUDED.short_description),
                description = COALESCE(product_translations.description, EXCLUDED.description),
                slug = COALESCE(product_translations.slug, EXCLUDED.slug)
            SQL
        );
        $this->addSql('DROP INDEX uniq_product_tenant_slug');
        $this->addSql('ALTER TABLE products DROP name');
        $this->addSql('ALTER TABLE products DROP slug');
        $this->addSql('ALTER TABLE products DROP short_description');
        $this->addSql('ALTER TABLE products DROP description');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD short_description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD description TEXT DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE products AS product
            SET
                name = translation.name,
                slug = translation.slug,
                short_description = translation.short_description,
                description = translation.description
            FROM product_translations AS translation
            INNER JOIN tenants AS tenant ON tenant.id = product.tenant_id
            INNER JOIN locales AS locale ON locale.id = translation.locale_id
            WHERE translation.product_id = product.id
              AND locale.code = tenant.default_snippet_locale
            SQL
        );
        $this->addSql("UPDATE products SET name = 'Product' WHERE name IS NULL");
        $this->addSql("UPDATE products SET slug = lower(replace(name, ' ', '-')) WHERE slug IS NULL");
        $this->addSql('ALTER TABLE products ALTER name SET NOT NULL');
        $this->addSql('ALTER TABLE products ALTER slug SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_tenant_slug ON products (tenant_id, slug)');
    }
}
