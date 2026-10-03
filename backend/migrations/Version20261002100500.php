<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002100500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track started catalogue writes so uncertain batch acknowledgements can reconcile safely.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integration_export_items ADD write_started BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integration_export_items DROP write_started');
    }
}
