<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allows tenant-scoped custom fields without a custom field set.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE custom_fields ADD tenant_id UUID DEFAULT NULL');
        $this->addSql('UPDATE custom_fields SET tenant_id = custom_field_sets.tenant_id FROM custom_field_sets WHERE custom_fields.custom_field_set_id = custom_field_sets.id');
        $this->addSql('ALTER TABLE custom_fields ALTER COLUMN tenant_id SET NOT NULL');
        $this->addSql('ALTER TABLE custom_fields ALTER COLUMN custom_field_set_id DROP NOT NULL');
        $this->addSql('ALTER TABLE custom_fields ADD CONSTRAINT fk_custom_field_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('DROP INDEX uniq_custom_field_name');
        $this->addSql('CREATE UNIQUE INDEX uniq_custom_field_name ON custom_fields (tenant_id, custom_field_set_id, technical_name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM custom_fields WHERE custom_field_set_id IS NULL');
        $this->addSql('DROP INDEX uniq_custom_field_name');
        $this->addSql('CREATE UNIQUE INDEX uniq_custom_field_name ON custom_fields (custom_field_set_id, technical_name)');
        $this->addSql('ALTER TABLE custom_fields DROP CONSTRAINT fk_custom_field_tenant');
        $this->addSql('ALTER TABLE custom_fields DROP tenant_id');
        $this->addSql('ALTER TABLE custom_fields ALTER COLUMN custom_field_set_id SET NOT NULL');
    }
}
