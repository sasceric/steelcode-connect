<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729080000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds locale translations for property groups and property values.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE property_group_translations (id UUID NOT NULL, property_group_id UUID NOT NULL, locale_id UUID NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_property_group_translation_locale ON property_group_translations (property_group_id, locale_id)');
        $this->addSql('CREATE TABLE property_translations (id UUID NOT NULL, property_id UUID NOT NULL, locale_id UUID NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_property_translation_locale ON property_translations (property_id, locale_id)');
        $this->addSql('ALTER TABLE property_group_translations ADD CONSTRAINT fk_property_group_translation_group FOREIGN KEY (property_group_id) REFERENCES property_groups (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE property_group_translations ADD CONSTRAINT fk_property_group_translation_locale FOREIGN KEY (locale_id) REFERENCES locales (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE property_translations ADD CONSTRAINT fk_property_translation_property FOREIGN KEY (property_id) REFERENCES properties (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE property_translations ADD CONSTRAINT fk_property_translation_locale FOREIGN KEY (locale_id) REFERENCES locales (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE property_translations'); $this->addSql('DROP TABLE property_group_translations'); }
}
