<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260728030000 extends AbstractMigration {
 public function getDescription(): string { return 'Adds canonical Shopware and WooCommerce product core fields.'; }
 public function up(Schema $schema): void {
  $this->addSql("ALTER TABLE products ADD product_type VARCHAR(32) NOT NULL DEFAULT 'physical'");
  $this->addSql('ALTER TABLE products ADD manufacturer_number VARCHAR(255) DEFAULT NULL');
  $this->addSql('ALTER TABLE products ADD tax_rate NUMERIC(5,2) DEFAULT NULL');
  $this->addSql('ALTER TABLE products ADD shipping_class VARCHAR(255) DEFAULT NULL');
  $this->addSql('ALTER TABLE products ADD delivery_time VARCHAR(255) DEFAULT NULL');
  $this->addSql('ALTER TABLE products ADD release_date DATE DEFAULT NULL');
  $this->addSql('ALTER TABLE products ADD is_featured BOOLEAN NOT NULL DEFAULT FALSE');
  $this->addSql('ALTER TABLE products ADD visibility JSON NOT NULL DEFAULT \'[]\'');
  $this->addSql('ALTER TABLE products ADD custom_fields JSON NOT NULL DEFAULT \'{}\'');
  $this->addSql('CREATE INDEX idx_products_type ON products (tenant_id, product_type, status)');
 }
 public function down(Schema $schema): void { $this->addSql('DROP INDEX idx_products_type'); foreach (['custom_fields','visibility','is_featured','release_date','delivery_time','shipping_class','tax_rate','manufacturer_number','product_type'] as $column) $this->addSql('ALTER TABLE products DROP '.$column); }
}
