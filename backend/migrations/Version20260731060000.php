<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds suppliers for purchase-order workflows.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE suppliers (id UUID NOT NULL, tenant_id UUID NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, phone VARCHAR(64) DEFAULT NULL, active BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_supplier_tenant_code ON suppliers (tenant_id, code)');
        $this->addSql('ALTER TABLE suppliers ADD CONSTRAINT FK_SUPPLIERS_TENANT FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE suppliers');
    }
}
