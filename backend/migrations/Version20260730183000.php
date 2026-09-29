<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds import stages and persistent integration import log entries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE integration_import_runs ADD current_stage VARCHAR(64) NOT NULL DEFAULT 'queued'",
        );
        $this->addSql(
            'CREATE TABLE integration_import_logs (
                id UUID NOT NULL,
                run_id UUID NOT NULL,
                level VARCHAR(16) NOT NULL,
                stage VARCHAR(64) NOT NULL,
                message TEXT NOT NULL,
                context JSON NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )',
        );
        $this->addSql('CREATE INDEX idx_integration_import_log_run_created ON integration_import_logs (run_id, created_at)');
        $this->addSql('ALTER TABLE integration_import_logs ADD CONSTRAINT fk_integration_import_log_run FOREIGN KEY (run_id) REFERENCES integration_import_runs (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE integration_import_logs');
        $this->addSql('ALTER TABLE integration_import_runs DROP current_stage');
    }
}
