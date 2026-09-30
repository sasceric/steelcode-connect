<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929124000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keeps imported customer profiles distinct from order-only customer snapshots and expands the customer address book.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE customers ADD customer_number VARCHAR(255) DEFAULT NULL, ADD account_type VARCHAR(32) DEFAULT NULL, ADD title VARCHAR(128) DEFAULT NULL, ADD active BOOLEAN DEFAULT NULL, ADD vat_ids JSON NOT NULL DEFAULT '[]'::json, ADD default_billing_address_external_id VARCHAR(128) DEFAULT NULL, ADD default_shipping_address_external_id VARCHAR(128) DEFAULT NULL, ADD profile_imported BOOLEAN NOT NULL DEFAULT FALSE");
        $this->addSql('CREATE INDEX idx_customer_tenant_number ON customers (tenant_id, customer_number)');
        $this->addSql('ALTER TABLE customer_addresses ADD department VARCHAR(255) DEFAULT NULL, ADD title VARCHAR(128) DEFAULT NULL, ADD additional_address_line1 VARCHAR(255) DEFAULT NULL, ADD additional_address_line2 VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_addresses DROP department, DROP title, DROP additional_address_line1, DROP additional_address_line2');
        $this->addSql('DROP INDEX idx_customer_tenant_number');
        $this->addSql('ALTER TABLE customers DROP customer_number, DROP account_type, DROP title, DROP active, DROP vat_ids, DROP default_billing_address_external_id, DROP default_shipping_address_external_id, DROP profile_imported');
    }
}
