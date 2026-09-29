<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260730030000 extends AbstractMigration {
 public function getDescription(): string { return 'Moves translatable product custom fields into product translations.'; }
 public function up(Schema $schema): void {
  $this->addSql("ALTER TABLE product_translations ADD custom_fields JSON NOT NULL DEFAULT '{}'");
  $this->addSql("INSERT INTO product_translations (id, product_id, locale_id, name, custom_fields) SELECT md5(random()::text || clock_timestamp()::text)::uuid, product.id, locale.id, product.name, product.custom_fields FROM products product CROSS JOIN locales locale WHERE locale.code = 'bs-BA' AND product.custom_fields::text <> '{}' AND NOT EXISTS (SELECT 1 FROM product_translations translation WHERE translation.product_id = product.id AND translation.locale_id = locale.id)");
  $this->addSql("UPDATE product_translations translation SET custom_fields = product.custom_fields FROM products product WHERE translation.product_id = product.id AND product.custom_fields::text <> '{}'");
  $this->addSql('ALTER TABLE products DROP custom_fields');
 }
 public function down(Schema $schema): void { $this->addSql("ALTER TABLE products ADD custom_fields JSON NOT NULL DEFAULT '{}'"); $this->addSql('ALTER TABLE product_translations DROP custom_fields'); }
}
