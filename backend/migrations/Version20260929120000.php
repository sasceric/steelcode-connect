<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds channel customer records, current customer addresses, and immutable commercial sales-order snapshots.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE customers (id UUID NOT NULL, tenant_id UUID NOT NULL, connection_id UUID DEFAULT NULL, external_id VARCHAR(128) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, first_name VARCHAR(128) DEFAULT NULL, last_name VARCHAR(128) DEFAULT NULL, company VARCHAR(255) DEFAULT NULL, phone VARCHAR(64) DEFAULT NULL, guest BOOLEAN NOT NULL, source_payload JSON NOT NULL DEFAULT '{}'::json, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_customer_connection_external ON customers (connection_id, external_id)');
        $this->addSql('CREATE INDEX idx_customer_tenant_email ON customers (tenant_id, email)');
        $this->addSql('ALTER TABLE customers ADD CONSTRAINT FK_CUSTOMER_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE customers ADD CONSTRAINT FK_CUSTOMER_CONNECTION FOREIGN KEY (connection_id) REFERENCES integration_connections (id) ON DELETE SET NULL');

        $this->addSql("CREATE TABLE customer_addresses (id UUID NOT NULL, customer_id UUID NOT NULL, external_id VARCHAR(128) DEFAULT NULL, first_name VARCHAR(128) DEFAULT NULL, last_name VARCHAR(128) DEFAULT NULL, company VARCHAR(255) DEFAULT NULL, street VARCHAR(255) DEFAULT NULL, zipcode VARCHAR(32) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, country_code VARCHAR(2) DEFAULT NULL, country_state VARCHAR(64) DEFAULT NULL, phone VARCHAR(64) DEFAULT NULL, billing_default BOOLEAN NOT NULL, shipping_default BOOLEAN NOT NULL, source_payload JSON NOT NULL DEFAULT '{}'::json, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql('CREATE UNIQUE INDEX uniq_customer_address_external ON customer_addresses (customer_id, external_id)');
        $this->addSql('ALTER TABLE customer_addresses ADD CONSTRAINT FK_CUSTOMER_ADDRESS_CUSTOMER FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE');

        $this->addSql("ALTER TABLE sales_orders ADD customer_id UUID DEFAULT NULL, ADD customer_snapshot JSON NOT NULL DEFAULT '{}'::json, ADD billing_address_snapshot JSON NOT NULL DEFAULT '{}'::json, ADD shipping_address_snapshot JSON NOT NULL DEFAULT '{}'::json, ADD currency_code VARCHAR(3) DEFAULT NULL, ADD tax_status VARCHAR(16) DEFAULT NULL, ADD amount_net NUMERIC(19, 4) DEFAULT NULL, ADD amount_tax NUMERIC(19, 4) DEFAULT NULL, ADD amount_gross NUMERIC(19, 4) DEFAULT NULL, ADD shipping_net NUMERIC(19, 4) DEFAULT NULL, ADD shipping_tax NUMERIC(19, 4) DEFAULT NULL, ADD shipping_gross NUMERIC(19, 4) DEFAULT NULL");
        $this->addSql('CREATE INDEX idx_sales_order_customer ON sales_orders (customer_id)');
        $this->addSql('ALTER TABLE sales_orders ADD CONSTRAINT FK_SALES_ORDER_CUSTOMER FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL');

    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales_orders DROP CONSTRAINT FK_SALES_ORDER_CUSTOMER');
        $this->addSql('DROP INDEX idx_sales_order_customer');
        $this->addSql('ALTER TABLE sales_orders DROP customer_id, DROP customer_snapshot, DROP billing_address_snapshot, DROP shipping_address_snapshot, DROP currency_code, DROP tax_status, DROP amount_net, DROP amount_tax, DROP amount_gross, DROP shipping_net, DROP shipping_tax, DROP shipping_gross');
        $this->addSql('DROP TABLE customer_addresses');
        $this->addSql('DROP TABLE customers');
    }
}
