<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260725030000 extends AbstractMigration {
 public function getDescription(): string { return 'Adds single-use password reset tokens.'; }
 public function up(Schema $schema): void { $this->addSql('CREATE TABLE password_reset_tokens (id UUID NOT NULL, user_id UUID NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))'); $this->addSql('CREATE UNIQUE INDEX uniq_password_reset_token_hash ON password_reset_tokens (token_hash)'); $this->addSql('CREATE INDEX idx_password_reset_user ON password_reset_tokens (user_id)'); $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE'); }
 public function down(Schema $schema): void { $this->addSql('ALTER TABLE password_reset_tokens DROP CONSTRAINT fk_password_reset_user'); $this->addSql('DROP TABLE password_reset_tokens'); }
}
