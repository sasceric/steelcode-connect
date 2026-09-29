<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds tenant integration connections and encrypted integration secrets.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE integration_connections (id UUID NOT NULL, tenant_id UUID NOT NULL, connector_key VARCHAR(32) NOT NULL, name VARCHAR(255) NOT NULL, directions JSON NOT NULL, configuration JSON NOT NULL, status VARCHAR(16) NOT NULL, enabled BOOLEAN NOT NULL, last_tested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, last_test_message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_integration_connection_name ON integration_connections (tenant_id, name)');
        $this->addSql('CREATE INDEX idx_integration_connections_tenant ON integration_connections (tenant_id)');
        $this->addSql('CREATE TABLE integration_secrets (id UUID NOT NULL, connection_id UUID NOT NULL, secret_key VARCHAR(64) NOT NULL, ciphertext TEXT NOT NULL, nonce VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_integration_secret_key ON integration_secrets (connection_id, secret_key)');
        $this->addSql('ALTER TABLE integration_connections ADD CONSTRAINT fk_integration_connections_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE integration_secrets ADD CONSTRAINT fk_integration_secrets_connection FOREIGN KEY (connection_id) REFERENCES integration_connections (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE integration_secrets');
        $this->addSql('DROP TABLE integration_connections');
    }
}
