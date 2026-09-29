<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stores the subscription billing interval on invoices.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE invoices ADD billing_interval VARCHAR(16) NOT NULL DEFAULT 'monthly'");
        $this->addSql("UPDATE invoices AS invoice SET billing_interval = COALESCE(tenant.subscription_interval, 'monthly') FROM tenants AS tenant WHERE tenant.id = invoice.tenant_id");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoices DROP billing_interval');
    }
}
