<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Makes partial purchase receipts idempotent under retries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE purchase_receipts ADD receipt_key UUID DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_purchase_receipt_key_line ON purchase_receipts (purchase_order_id, receipt_key, item_id) WHERE receipt_key IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_purchase_receipt_key_line');
        $this->addSql('ALTER TABLE purchase_receipts DROP receipt_key');
    }
}
