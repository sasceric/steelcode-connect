<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds persisted integration import progress and the asynchronous Messenger transport table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE integration_import_runs (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                connection_id UUID NOT NULL,
                type VARCHAR(64) NOT NULL,
                status VARCHAR(16) NOT NULL,
                total_items INT NOT NULL,
                processed_items INT NOT NULL,
                created_items INT NOT NULL,
                updated_items INT NOT NULL,
                failed_items INT NOT NULL,
                failure_reason TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )',
        );
        $this->addSql('CREATE INDEX idx_integration_import_run_connection_created ON integration_import_runs (connection_id, created_at)');
        $this->addSql("CREATE UNIQUE INDEX uniq_integration_import_run_active ON integration_import_runs (connection_id, type) WHERE status IN ('queued', 'running')");
        $this->addSql('ALTER TABLE integration_import_runs ADD CONSTRAINT fk_integration_import_run_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE integration_import_runs ADD CONSTRAINT fk_integration_import_run_connection FOREIGN KEY (connection_id) REFERENCES integration_connections (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(
            'CREATE TABLE messenger_messages (
                id BIGSERIAL NOT NULL,
                body TEXT NOT NULL,
                headers TEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )',
        );
        $this->addSql('CREATE INDEX idx_messenger_messages_queue ON messenger_messages (queue_name, available_at, delivered_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('DROP TABLE integration_import_runs');
    }
}
