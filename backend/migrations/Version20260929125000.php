<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929125000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserves channel payment and shipping method identities, all tracking codes, and delivery positions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales_order_payments ADD method_external_id VARCHAR(128) DEFAULT NULL');
        $this->addSql("ALTER TABLE sales_order_deliveries ADD method_external_id VARCHAR(128) DEFAULT NULL, ADD tracking_codes JSON NOT NULL DEFAULT '[]'::json, ADD positions_snapshot JSON NOT NULL DEFAULT '[]'::json, ADD shipping_gross NUMERIC(19, 4) DEFAULT NULL");
        $this->addSql("UPDATE sales_order_deliveries SET tracking_codes = json_build_array(tracking_number) WHERE tracking_number IS NOT NULL AND tracking_number <> ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales_order_deliveries DROP method_external_id, DROP tracking_codes, DROP positions_snapshot, DROP shipping_gross');
        $this->addSql('ALTER TABLE sales_order_payments DROP method_external_id');
    }
}
