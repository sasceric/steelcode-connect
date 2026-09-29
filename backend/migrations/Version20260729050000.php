<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260729050000 extends AbstractMigration {
 public function getDescription(): string { return 'Renames property default media relation to property media relation.'; }
 public function up(Schema $schema): void { $this->addSql('ALTER TABLE properties RENAME COLUMN default_media_id TO media_id'); $this->addSql('ALTER INDEX idx_properties_default_media RENAME TO idx_properties_media'); $this->addSql('ALTER TABLE properties RENAME CONSTRAINT fk_properties_default_media TO fk_properties_media'); }
 public function down(Schema $schema): void { $this->addSql('ALTER TABLE properties RENAME COLUMN media_id TO default_media_id'); $this->addSql('ALTER INDEX idx_properties_media RENAME TO idx_properties_default_media'); $this->addSql('ALTER TABLE properties RENAME CONSTRAINT fk_properties_media TO fk_properties_default_media'); }
}
