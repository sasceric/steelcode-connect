<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tracks physical quarantine of newly damaged purchase receipts without inventing stock for historic receipts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE purchase_receipts ADD quarantine_quantity NUMERIC(19, 4) NOT NULL DEFAULT 0, ADD quarantine_status VARCHAR(24) DEFAULT NULL");
        $this->addSql("UPDATE purchase_receipts SET quarantine_status = 'legacy_untracked' WHERE damaged_quantity > 0");
        $this->addSql('ALTER TABLE purchase_receipts ALTER quarantine_quantity DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase_receipts ADD CONSTRAINT chk_receipt_quarantine_quantity CHECK (quarantine_quantity >= 0)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE purchase_receipts DROP CONSTRAINT chk_receipt_quarantine_quantity');
        $this->addSql('ALTER TABLE purchase_receipts DROP quarantine_quantity, DROP quarantine_status');
    }
}
