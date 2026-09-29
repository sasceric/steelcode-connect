<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260729060000 extends AbstractMigration {
 public function getDescription(): string { return 'Migrates product media files into shared media ownership and adds product media relation.'; }
 public function up(Schema $schema): void {
  $this->addSql('ALTER TABLE product_media ADD media_id UUID DEFAULT NULL');
  $this->addSql("INSERT INTO media (id, tenant_id, storage_key, file_name, file_extension, file_size, mime_type, checksum, created_at, updated_at) SELECT id, tenant_id, 'product-media/' || storage_key, file_name, file_extension, file_size, mime_type, checksum, created_at, updated_at FROM product_media");
  $this->addSql('UPDATE product_media SET media_id = id');
  $this->addSql('ALTER TABLE product_media ALTER media_id SET NOT NULL');
  $this->addSql('CREATE INDEX idx_product_media_media ON product_media (media_id)');
  $this->addSql('ALTER TABLE product_media ADD CONSTRAINT fk_product_media_media FOREIGN KEY (media_id) REFERENCES media (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
 }
 public function down(Schema $schema): void { $this->addSql('ALTER TABLE product_media DROP media_id'); }
}
