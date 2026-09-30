<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stores manually reconciled cumulative shipped quantities for ambiguous Shopware partial deliveries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sales_orders ADD manual_shipment_targets JSON NOT NULL DEFAULT '{}'::json");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales_orders DROP manual_shipment_targets');
    }
}
