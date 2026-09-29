<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260724234500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates tenants, users and tenant memberships for session authentication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tenants (id UUID NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');
        $this->addSql('CREATE TABLE tenant_memberships (id UUID NOT NULL, tenant_id UUID NOT NULL, user_id UUID NOT NULL, role VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_membership_tenant ON tenant_memberships (tenant_id)');
        $this->addSql('CREATE INDEX idx_membership_user ON tenant_memberships (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_membership_tenant_user ON tenant_memberships (tenant_id, user_id)');
        $this->addSql('ALTER TABLE tenant_memberships ADD CONSTRAINT fk_membership_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE tenant_memberships ADD CONSTRAINT fk_membership_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_memberships DROP CONSTRAINT fk_membership_tenant');
        $this->addSql('ALTER TABLE tenant_memberships DROP CONSTRAINT fk_membership_user');
        $this->addSql('DROP TABLE tenant_memberships');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE tenants');
    }
}
