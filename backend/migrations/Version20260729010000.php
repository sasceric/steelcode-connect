<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729010000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds canonical property groups, properties and product property assignments.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE property_groups (id UUID NOT NULL, tenant_id UUID NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(100) NOT NULL, display_type VARCHAR(16) NOT NULL, is_filterable BOOLEAN NOT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_property_group_code ON property_groups (tenant_id, code)');
        $this->addSql('CREATE TABLE properties (id UUID NOT NULL, tenant_id UUID NOT NULL, property_group_id UUID NOT NULL, name VARCHAR(255) NOT NULL, code VARCHAR(100) NOT NULL, color_hex VARCHAR(7) DEFAULT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_property_code ON properties (property_group_id, code)');
        $this->addSql('CREATE TABLE product_property_assignments (id UUID NOT NULL, tenant_id UUID NOT NULL, product_id UUID NOT NULL, property_id UUID NOT NULL, source VARCHAR(32) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_property_assignment ON product_property_assignments (product_id, property_id)');
        $this->addSql('ALTER TABLE property_groups ADD CONSTRAINT fk_property_groups_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE properties ADD CONSTRAINT fk_properties_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE properties ADD CONSTRAINT fk_properties_group FOREIGN KEY (property_group_id) REFERENCES property_groups (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_property_assignments ADD CONSTRAINT fk_product_property_assignments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_property_assignments ADD CONSTRAINT fk_product_property_assignments_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_property_assignments ADD CONSTRAINT fk_product_property_assignments_property FOREIGN KEY (property_id) REFERENCES properties (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product_property_assignments');
        $this->addSql('DROP TABLE properties');
        $this->addSql('DROP TABLE property_groups');
    }
}
