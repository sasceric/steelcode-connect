<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keeps the last failed stock-publication reason visible in the exception workbench.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_sync_outbox ADD last_error TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_sync_outbox DROP last_error');
    }
}
