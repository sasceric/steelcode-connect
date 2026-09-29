<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260728060000 extends AbstractMigration {
 public function getDescription(): string { return 'Adds namespaced product extension values for Shopware custom fields and WooCommerce metadata.'; }
 public function up(Schema $schema): void { $this->addSql('CREATE TABLE product_extension_values (id UUID NOT NULL, tenant_id UUID NOT NULL, product_id UUID NOT NULL, locale_id UUID DEFAULT NULL, namespace VARCHAR(128) NOT NULL, field_key VARCHAR(255) NOT NULL, value JSON NOT NULL, position INT NOT NULL, source VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))'); $this->addSql('CREATE UNIQUE INDEX uniq_product_extension_value ON product_extension_values (product_id, locale_id, namespace, field_key, position)'); $this->addSql('ALTER TABLE product_extension_values ADD CONSTRAINT fk_product_extension_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE'); $this->addSql('ALTER TABLE product_extension_values ADD CONSTRAINT fk_product_extension_locale FOREIGN KEY (locale_id) REFERENCES locales (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE'); }
 public function down(Schema $schema): void { $this->addSql('DROP TABLE product_extension_values'); }
}
