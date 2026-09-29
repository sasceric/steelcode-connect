<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds translated manufacturers with media and custom fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE manufacturers ADD media_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE manufacturers ADD CONSTRAINT fk_manufacturer_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE TABLE manufacturer_translations (id UUID NOT NULL, manufacturer_id UUID NOT NULL, locale_id UUID NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, meta_title VARCHAR(255) DEFAULT NULL, meta_description TEXT DEFAULT NULL, meta_keywords TEXT DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, custom_fields JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_manufacturer_translation_locale ON manufacturer_translations (manufacturer_id, locale_id)');
        $this->addSql('ALTER TABLE manufacturer_translations ADD CONSTRAINT fk_manufacturer_translation_manufacturer FOREIGN KEY (manufacturer_id) REFERENCES manufacturers (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE manufacturer_translations ADD CONSTRAINT fk_manufacturer_translation_locale FOREIGN KEY (locale_id) REFERENCES locales (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql(<<<'SQL'
            INSERT INTO manufacturer_translations (
                id, manufacturer_id, locale_id, name, description,
                meta_title, meta_description, meta_keywords, slug, custom_fields
            )
            SELECT
                md5(random()::text || clock_timestamp()::text)::uuid,
                manufacturer.id,
                locale.id,
                manufacturer.name,
                manufacturer.description,
                NULL,
                NULL,
                NULL,
                manufacturer.slug,
                '{}'::json
            FROM manufacturers AS manufacturer
            INNER JOIN tenants AS tenant ON tenant.id = manufacturer.tenant_id
            INNER JOIN locales AS locale ON locale.code = tenant.default_snippet_locale
            SQL
        );
        $this->addSql('DROP INDEX uniq_manufacturer_tenant_slug');
        $this->addSql('ALTER TABLE manufacturers DROP name');
        $this->addSql('ALTER TABLE manufacturers DROP slug');
        $this->addSql('ALTER TABLE manufacturers DROP description');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE manufacturers ADD name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE manufacturers ADD slug VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE manufacturers ADD description TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE manufacturers DROP CONSTRAINT fk_manufacturer_media');
        $this->addSql('ALTER TABLE manufacturers DROP media_id');
        $this->addSql('DROP TABLE manufacturer_translations');
    }
}
