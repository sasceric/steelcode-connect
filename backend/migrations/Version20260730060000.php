<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds canonical taxes, units, delivery times, and external entity mappings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE taxes (id UUID NOT NULL, tenant_id UUID NOT NULL, name VARCHAR(255) NOT NULL, rate NUMERIC(5, 2) NOT NULL, active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_tax_tenant_name ON taxes (tenant_id, name)');
        $this->addSql('ALTER TABLE taxes ADD CONSTRAINT fk_tax_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE units (id UUID NOT NULL, tenant_id UUID NOT NULL, code VARCHAR(64) NOT NULL, symbol VARCHAR(64) NOT NULL, labels JSON NOT NULL, active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_unit_tenant_code ON units (tenant_id, code)');
        $this->addSql('ALTER TABLE units ADD CONSTRAINT fk_unit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE delivery_times (id UUID NOT NULL, tenant_id UUID NOT NULL, labels JSON NOT NULL, min INT NOT NULL, max INT NOT NULL, unit VARCHAR(16) NOT NULL, active BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE delivery_times ADD CONSTRAINT fk_delivery_time_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE integration_entity_mappings (id UUID NOT NULL, tenant_id UUID NOT NULL, connection_id UUID NOT NULL, entity_type VARCHAR(64) NOT NULL, external_id VARCHAR(64) NOT NULL, local_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_integration_entity_mapping_source ON integration_entity_mappings (connection_id, entity_type, external_id)');
        $this->addSql('ALTER TABLE integration_entity_mappings ADD CONSTRAINT fk_integration_entity_mapping_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE integration_entity_mappings ADD CONSTRAINT fk_integration_entity_mapping_connection FOREIGN KEY (connection_id) REFERENCES integration_connections (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE integration_entity_mappings');
        $this->addSql('DROP TABLE delivery_times');
        $this->addSql('DROP TABLE units');
        $this->addSql('DROP TABLE taxes');
    }
}
