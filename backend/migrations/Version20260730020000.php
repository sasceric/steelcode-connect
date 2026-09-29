<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260730020000 extends AbstractMigration {
 public function getDescription(): string { return 'Adds Shopware-compatible typed custom field definitions.'; }
 public function up(Schema $schema): void {
  $this->addSql('CREATE TABLE custom_field_sets (id UUID NOT NULL, tenant_id UUID NOT NULL, technical_name VARCHAR(100) NOT NULL, labels JSON NOT NULL, relations JSON NOT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
  $this->addSql('CREATE UNIQUE INDEX uniq_custom_field_set_name ON custom_field_sets (tenant_id, technical_name)');
  $this->addSql('CREATE TABLE custom_fields (id UUID NOT NULL, custom_field_set_id UUID NOT NULL, technical_name VARCHAR(100) NOT NULL, type VARCHAR(32) NOT NULL, labels JSON NOT NULL, config JSON NOT NULL, position INT NOT NULL, PRIMARY KEY(id))');
  $this->addSql('CREATE UNIQUE INDEX uniq_custom_field_name ON custom_fields (custom_field_set_id, technical_name)');
  $this->addSql('CREATE TABLE custom_field_options (id UUID NOT NULL, custom_field_id UUID NOT NULL, technical_value VARCHAR(100) NOT NULL, labels JSON NOT NULL, position INT NOT NULL, PRIMARY KEY(id))');
  $this->addSql('CREATE UNIQUE INDEX uniq_custom_field_option_value ON custom_field_options (custom_field_id, technical_value)');
  $this->addSql('ALTER TABLE custom_field_sets ADD CONSTRAINT fk_custom_field_set_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
  $this->addSql('ALTER TABLE custom_fields ADD CONSTRAINT fk_custom_field_set FOREIGN KEY (custom_field_set_id) REFERENCES custom_field_sets (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
  $this->addSql('ALTER TABLE custom_field_options ADD CONSTRAINT fk_custom_field_option_field FOREIGN KEY (custom_field_id) REFERENCES custom_fields (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
 }
 public function down(Schema $schema): void { $this->addSql('DROP TABLE custom_field_options'); $this->addSql('DROP TABLE custom_fields'); $this->addSql('DROP TABLE custom_field_sets'); }
}
