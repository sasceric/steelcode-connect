<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260725020000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds tenant details and reusable tenant-owned addresses.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants ADD oib VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE tenants ADD pdv VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE tenants ADD phone VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE tenants ADD email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE tenants ADD website VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE TABLE addresses (id UUID NOT NULL, tenant_id UUID DEFAULT NULL, owner_type VARCHAR(64) NOT NULL, owner_id UUID DEFAULT NULL, country VARCHAR(2) NOT NULL, company VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, street VARCHAR(255) NOT NULL, zipcode VARCHAR(32) NOT NULL, city VARCHAR(255) NOT NULL, country_state VARCHAR(255) DEFAULT NULL, phone VARCHAR(32) DEFAULT NULL, additional_address_line1 VARCHAR(255) DEFAULT NULL, additional_address_line2 VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_addresses_tenant ON addresses (tenant_id)');
        $this->addSql('CREATE INDEX idx_addresses_owner ON addresses (owner_type, owner_id)');
        $this->addSql('ALTER TABLE addresses ADD CONSTRAINT fk_addresses_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE addresses DROP CONSTRAINT fk_addresses_tenant');
        $this->addSql('DROP TABLE addresses');
        $this->addSql('ALTER TABLE tenants DROP oib, DROP pdv, DROP phone, DROP email, DROP website');
    }
}
