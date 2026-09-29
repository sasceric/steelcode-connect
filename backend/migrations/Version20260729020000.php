<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729020000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds canonical product variant option configuration and selections.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_variant_option_groups (id UUID NOT NULL, tenant_id UUID NOT NULL, product_id UUID NOT NULL, property_group_id UUID NOT NULL, position INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_variant_option_group ON product_variant_option_groups (product_id, property_group_id)');
        $this->addSql('CREATE TABLE product_variant_option_group_properties (id UUID NOT NULL, tenant_id UUID NOT NULL, option_group_id UUID NOT NULL, property_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_variant_option_group_property ON product_variant_option_group_properties (option_group_id, property_id)');
        $this->addSql('CREATE TABLE product_variant_option_values (id UUID NOT NULL, tenant_id UUID NOT NULL, product_id UUID NOT NULL, property_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_variant_option_value ON product_variant_option_values (product_id, property_id)');
        $this->addSql('ALTER TABLE product_variant_option_groups ADD CONSTRAINT fk_variant_option_group_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_variant_option_groups ADD CONSTRAINT fk_variant_option_group_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_variant_option_groups ADD CONSTRAINT fk_variant_option_group_property_group FOREIGN KEY (property_group_id) REFERENCES property_groups (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_variant_option_group_properties ADD CONSTRAINT fk_variant_group_property_group FOREIGN KEY (option_group_id) REFERENCES product_variant_option_groups (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_variant_option_group_properties ADD CONSTRAINT fk_variant_group_property FOREIGN KEY (property_id) REFERENCES properties (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_variant_option_values ADD CONSTRAINT fk_variant_option_value_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_variant_option_values ADD CONSTRAINT fk_variant_option_value_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_variant_option_values ADD CONSTRAINT fk_variant_option_value_property FOREIGN KEY (property_id) REFERENCES properties (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE product_variant_option_values'); $this->addSql('DROP TABLE product_variant_option_group_properties'); $this->addSql('DROP TABLE product_variant_option_groups'); }
}
