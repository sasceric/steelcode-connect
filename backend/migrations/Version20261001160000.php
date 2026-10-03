<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Debounced catalogue outbox, leased reconciliation and resumable export checkpoints.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integration_catalogue_dirty ADD changed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE integration_catalogue_dirty ADD first_changed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('CREATE INDEX idx_catalogue_dirty_due ON integration_catalogue_dirty (tenant_id, connection_id, changed_at, first_changed_at)');
        $this->addSql('ALTER TABLE integration_catalogue_sync_state ADD queued_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE integration_catalogue_sync_state ADD queued_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("ALTER TABLE integration_export_plans ADD work_phase VARCHAR(16) DEFAULT NULL, ADD selection_cursor UUID DEFAULT NULL, ADD work_token UUID DEFAULT NULL, ADD retry_count INT NOT NULL DEFAULT 0");
        $this->addSql('ALTER TABLE integration_export_items ADD preflight_checked BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integration_export_items DROP preflight_checked');
        $this->addSql('ALTER TABLE integration_export_plans DROP work_phase, DROP selection_cursor, DROP work_token, DROP retry_count');
        $this->addSql('ALTER TABLE integration_catalogue_sync_state DROP queued_until, DROP queued_at');
        $this->addSql('DROP INDEX idx_catalogue_dirty_due');
        $this->addSql('ALTER TABLE integration_catalogue_dirty DROP changed_at, DROP first_changed_at');
    }
}
