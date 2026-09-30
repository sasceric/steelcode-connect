<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds per-product, per-warehouse replenishment targets and lead-time overrides.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_levels ADD reorder_target NUMERIC(19, 4) DEFAULT NULL, ADD reorder_lead_days INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_inventory_reorder_policy ON inventory_levels (tenant_id, warehouse_id) WHERE reorder_threshold IS NOT NULL');
        $this->addSql('ALTER TABLE inventory_levels ADD CONSTRAINT chk_reorder_policy CHECK ((reorder_threshold IS NULL AND reorder_target IS NULL) OR (reorder_threshold >= 0 AND reorder_target > reorder_threshold))');
        $this->addSql('ALTER TABLE inventory_levels ADD CONSTRAINT chk_reorder_lead_days CHECK (reorder_lead_days IS NULL OR (reorder_lead_days >= 0 AND reorder_lead_days <= 3650))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_levels DROP CONSTRAINT chk_reorder_lead_days');
        $this->addSql('ALTER TABLE inventory_levels DROP CONSTRAINT chk_reorder_policy');
        $this->addSql('DROP INDEX idx_inventory_reorder_policy');
        $this->addSql('ALTER TABLE inventory_levels DROP reorder_target, DROP reorder_lead_days');
    }
}
