<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replaces reference-data label JSON with localized translation tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tax_translations (id UUID NOT NULL, tax_id UUID NOT NULL, locale_id UUID NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_tax_translation_locale ON tax_translations (tax_id, locale_id)');
        $this->addSql('ALTER TABLE tax_translations ADD CONSTRAINT fk_tax_translation_tax FOREIGN KEY (tax_id) REFERENCES taxes (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tax_translations ADD CONSTRAINT fk_tax_translation_locale FOREIGN KEY (locale_id) REFERENCES locales (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE unit_translations (id UUID NOT NULL, unit_id UUID NOT NULL, locale_id UUID NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_unit_translation_locale ON unit_translations (unit_id, locale_id)');
        $this->addSql('ALTER TABLE unit_translations ADD CONSTRAINT fk_unit_translation_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE unit_translations ADD CONSTRAINT fk_unit_translation_locale FOREIGN KEY (locale_id) REFERENCES locales (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE delivery_time_translations (id UUID NOT NULL, delivery_time_id UUID NOT NULL, locale_id UUID NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_delivery_time_translation_locale ON delivery_time_translations (delivery_time_id, locale_id)');
        $this->addSql('ALTER TABLE delivery_time_translations ADD CONSTRAINT fk_delivery_time_translation_delivery_time FOREIGN KEY (delivery_time_id) REFERENCES delivery_times (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE delivery_time_translations ADD CONSTRAINT fk_delivery_time_translation_locale FOREIGN KEY (locale_id) REFERENCES locales (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("INSERT INTO unit_translations (id, unit_id, locale_id, name) SELECT md5(random()::text || clock_timestamp()::text)::uuid, units.id, locales.id, COALESCE(units.labels->>'bs-BA', units.code) FROM units JOIN locales ON locales.code = 'bs-BA'");
        $this->addSql("INSERT INTO delivery_time_translations (id, delivery_time_id, locale_id, name) SELECT md5(random()::text || clock_timestamp()::text)::uuid, delivery_times.id, locales.id, COALESCE(delivery_times.labels->>'bs-BA', '') FROM delivery_times JOIN locales ON locales.code = 'bs-BA'");
        $this->addSql("INSERT INTO tax_translations (id, tax_id, locale_id, name) SELECT md5(random()::text || clock_timestamp()::text)::uuid, taxes.id, locales.id, taxes.name FROM taxes JOIN locales ON locales.code = 'bs-BA'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE delivery_time_translations');
        $this->addSql('DROP TABLE unit_translations');
        $this->addSql('DROP TABLE tax_translations');
    }
}
