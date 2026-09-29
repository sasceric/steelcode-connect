<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260728050000 extends AbstractMigration {
 public function getDescription(): string { return 'Adds translated product SEO keywords.'; }
 public function up(Schema $schema): void { $this->addSql('ALTER TABLE product_translations ADD meta_keywords TEXT DEFAULT NULL'); }
 public function down(Schema $schema): void { $this->addSql('ALTER TABLE product_translations DROP meta_keywords'); }
}
