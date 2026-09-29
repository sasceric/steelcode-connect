<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726010000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds tenant subscriptions and monthly invoices.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants ADD subscription_plan VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE tenants ADD subscription_price INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tenants ADD subscription_started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE TABLE invoices (id UUID NOT NULL, tenant_id UUID NOT NULL, number VARCHAR(64) NOT NULL, plan VARCHAR(32) NOT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL, billing_period DATE NOT NULL, status VARCHAR(32) NOT NULL, issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_invoice_number ON invoices (number)');
        $this->addSql('CREATE UNIQUE INDEX uniq_invoice_tenant_period ON invoices (tenant_id, billing_period)');
        $this->addSql('CREATE INDEX idx_invoices_tenant ON invoices (tenant_id)');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoices_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT fk_invoices_tenant');
        $this->addSql('DROP TABLE invoices');
        $this->addSql('ALTER TABLE tenants DROP subscription_plan');
        $this->addSql('ALTER TABLE tenants DROP subscription_price');
        $this->addSql('ALTER TABLE tenants DROP subscription_started_at');
    }
}
