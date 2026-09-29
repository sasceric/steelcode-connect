<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds unavailable and incoming quantities to warehouse inventory levels.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE inventory_levels ADD unavailable_quantity NUMERIC(19, 4) NOT NULL DEFAULT '0.0000'",
        );
        $this->addSql(
            "ALTER TABLE inventory_levels ADD incoming_quantity NUMERIC(19, 4) NOT NULL DEFAULT '0.0000'",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_levels DROP unavailable_quantity');
        $this->addSql('ALTER TABLE inventory_levels DROP incoming_quantity');
    }
}
