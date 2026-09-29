<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Moves canonical category names and slugs into category translations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO category_translations (
                id,
                category_id,
                locale_id,
                name,
                description,
                meta_title,
                meta_description,
                meta_keywords,
                slug,
                custom_fields
            )
            SELECT
                md5(random()::text || clock_timestamp()::text)::uuid,
                category.id,
                locale.id,
                category.name,
                NULL,
                NULL,
                NULL,
                NULL,
                category.slug,
                '{}'::json
            FROM categories AS category
            INNER JOIN tenants AS tenant ON tenant.id = category.tenant_id
            INNER JOIN locales AS locale ON locale.code = tenant.default_snippet_locale
            ON CONFLICT (category_id, locale_id) DO UPDATE SET
                name = EXCLUDED.name,
                slug = COALESCE(category_translations.slug, EXCLUDED.slug)
            SQL
        );
        $this->addSql('ALTER TABLE categories DROP name');
        $this->addSql('ALTER TABLE categories DROP slug');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE categories ADD name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE categories ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE categories AS category
            SET
                name = translation.name,
                slug = translation.slug
            FROM category_translations AS translation
            INNER JOIN tenants AS tenant ON tenant.id = category.tenant_id
            INNER JOIN locales AS locale ON locale.id = translation.locale_id
            WHERE translation.category_id = category.id
              AND locale.code = tenant.default_snippet_locale
            SQL
        );
        $this->addSql("UPDATE categories SET name = 'Category' WHERE name IS NULL");
        $this->addSql("UPDATE categories SET slug = lower(replace(name, ' ', '-')) WHERE slug IS NULL");
        $this->addSql('ALTER TABLE categories ALTER name SET NOT NULL');
        $this->addSql('ALTER TABLE categories ALTER slug SET NOT NULL');
    }
}
