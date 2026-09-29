<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260729030000 extends AbstractMigration {
 public function getDescription(): string { return 'Adds Shopware-style display settings to property groups.'; }
 public function up(Schema $schema): void { $this->addSql('ALTER TABLE property_groups ADD display_on_product_detail BOOLEAN NOT NULL DEFAULT TRUE'); $this->addSql("ALTER TABLE property_groups ADD sorting VARCHAR(16) NOT NULL DEFAULT 'custom'"); }
 public function down(Schema $schema): void { $this->addSql('ALTER TABLE property_groups DROP display_on_product_detail, DROP sorting'); }
}
