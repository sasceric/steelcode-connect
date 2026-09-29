<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Versions inventory levels so stale stock counts cannot overwrite later movements.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_levels ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE inventory_count_items ADD expected_version INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_count_items DROP expected_version');
        $this->addSql('ALTER TABLE inventory_levels DROP version');
    }
}
