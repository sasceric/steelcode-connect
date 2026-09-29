<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds fulfilment settings and priority to warehouses.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE warehouses ADD fulfillment_enabled BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE warehouses ADD priority INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE warehouses DROP fulfillment_enabled');
        $this->addSql('ALTER TABLE warehouses DROP priority');
    }
}
