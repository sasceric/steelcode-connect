<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds immutable commercial price and tax snapshots to sales order lines.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sales_order_items ADD currency_code VARCHAR(3) DEFAULT NULL, ADD unit_net NUMERIC(19, 4) DEFAULT NULL, ADD unit_gross NUMERIC(19, 4) DEFAULT NULL, ADD total_net NUMERIC(19, 4) DEFAULT NULL, ADD total_tax NUMERIC(19, 4) DEFAULT NULL, ADD total_gross NUMERIC(19, 4) DEFAULT NULL, ADD discount_gross NUMERIC(19, 4) DEFAULT NULL, ADD tax_snapshot JSON NOT NULL DEFAULT '{}'::json");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales_order_items DROP currency_code, DROP unit_net, DROP unit_gross, DROP total_net, DROP total_tax, DROP total_gross, DROP discount_gross, DROP tax_snapshot');
    }
}
