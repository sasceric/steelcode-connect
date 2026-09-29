<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Records supplier email delivery and damaged-goods resolution history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE purchase_orders ADD last_emailed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD last_emailed_to VARCHAR(255) DEFAULT NULL');
        $this->addSql("ALTER TABLE purchase_receipts ADD damage_history JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE purchase_receipts ALTER damage_history DROP DEFAULT');
        $this->addSql('ALTER TABLE supplier_offers ALTER purchase_unit DROP DEFAULT, ALTER stock_units_per_purchase_unit DROP DEFAULT');
        $this->addSql('ALTER TABLE purchase_order_items ALTER purchase_unit DROP DEFAULT, ALTER stock_units_per_purchase_unit DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE purchase_receipts DROP damage_history');
        $this->addSql('ALTER TABLE purchase_orders DROP last_emailed_at, DROP last_emailed_to');
    }
}
