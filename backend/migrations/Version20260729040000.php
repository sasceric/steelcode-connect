<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260729040000 extends AbstractMigration {
 public function getDescription(): string { return 'Adds shared media library and property media relation.'; }
 public function up(Schema $schema): void {
  $this->addSql('CREATE TABLE media (id UUID NOT NULL, tenant_id UUID NOT NULL, storage_key VARCHAR(512) NOT NULL, file_name VARCHAR(255) NOT NULL, file_extension VARCHAR(20) DEFAULT NULL, file_size INT DEFAULT NULL, mime_type VARCHAR(127) DEFAULT NULL, checksum VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
  $this->addSql('CREATE INDEX idx_media_tenant_checksum ON media (tenant_id, checksum)');
  $this->addSql('ALTER TABLE properties ADD media_id UUID DEFAULT NULL');
  $this->addSql('CREATE INDEX idx_properties_media ON properties (media_id)');
  $this->addSql('ALTER TABLE media ADD CONSTRAINT fk_media_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
  $this->addSql('ALTER TABLE properties ADD CONSTRAINT fk_properties_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
 }
 public function down(Schema $schema): void { $this->addSql('ALTER TABLE properties DROP media_id'); $this->addSql('DROP TABLE media'); }
}
