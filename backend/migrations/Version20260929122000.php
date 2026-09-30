<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929122000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds immutable payment and delivery snapshots to sales orders.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE sales_order_payments (id UUID NOT NULL, sales_order_id UUID NOT NULL, external_id VARCHAR(128) NOT NULL, method_name VARCHAR(255) DEFAULT NULL, state VARCHAR(64) DEFAULT NULL, reference VARCHAR(128) DEFAULT NULL, currency_code VARCHAR(3) DEFAULT NULL, amount NUMERIC(19, 4) DEFAULT NULL, source_payload JSON NOT NULL DEFAULT '{}'::json, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_sales_order_payment_external ON sales_order_payments (sales_order_id, external_id)');
        $this->addSql('ALTER TABLE sales_order_payments ADD CONSTRAINT FK_SALES_ORDER_PAYMENT_ORDER FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE CASCADE');

        $this->addSql("CREATE TABLE sales_order_deliveries (id UUID NOT NULL, sales_order_id UUID NOT NULL, external_id VARCHAR(128) NOT NULL, method_name VARCHAR(255) DEFAULT NULL, state VARCHAR(64) DEFAULT NULL, tracking_number VARCHAR(255) DEFAULT NULL, shipping_address_snapshot JSON NOT NULL DEFAULT '{}'::json, source_payload JSON NOT NULL DEFAULT '{}'::json, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_sales_order_delivery_external ON sales_order_deliveries (sales_order_id, external_id)');
        $this->addSql('ALTER TABLE sales_order_deliveries ADD CONSTRAINT FK_SALES_ORDER_DELIVERY_ORDER FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sales_order_deliveries');
        $this->addSql('DROP TABLE sales_order_payments');
    }
}
