<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929129000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Exposes the last incremental Sales sync failure to the integration screen.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integration_sales_sync_cursors ADD last_error TEXT DEFAULT NULL, ADD last_error_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integration_sales_sync_cursors DROP last_error, DROP last_error_at');
    }
}
