<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds integration sales channels and per-product channel visibility.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE integration_sales_channels (id UUID NOT NULL, tenant_id UUID NOT NULL, connection_id UUID NOT NULL, external_id VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(64) DEFAULT NULL, active BOOLEAN NOT NULL, source_data JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_integration_sales_channel_external ON integration_sales_channels (connection_id, external_id)');
        $this->addSql('CREATE INDEX idx_integration_sales_channel_tenant ON integration_sales_channels (tenant_id, active)');
        $this->addSql('ALTER TABLE integration_sales_channels ADD CONSTRAINT fk_integration_sales_channel_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE integration_sales_channels ADD CONSTRAINT fk_integration_sales_channel_connection FOREIGN KEY (connection_id) REFERENCES integration_connections (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE product_channel_publications (id UUID NOT NULL, tenant_id UUID NOT NULL, product_id UUID NOT NULL, sales_channel_id UUID NOT NULL, visibility INT NOT NULL, external_product_id VARCHAR(64) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_channel_publication ON product_channel_publications (product_id, sales_channel_id)');
        $this->addSql('CREATE INDEX idx_product_channel_publication_tenant ON product_channel_publications (tenant_id, product_id)');
        $this->addSql('ALTER TABLE product_channel_publications ADD CONSTRAINT fk_product_channel_publication_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_channel_publications ADD CONSTRAINT fk_product_channel_publication_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_channel_publications ADD CONSTRAINT fk_product_channel_publication_channel FOREIGN KEY (sales_channel_id) REFERENCES integration_sales_channels (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product_channel_publications');
        $this->addSql('DROP TABLE integration_sales_channels');
    }
}
