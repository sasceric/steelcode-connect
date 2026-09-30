<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929127000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stores a per-connection checkpoint for incremental Shopware Sales synchronization.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE integration_sales_sync_cursors (id UUID NOT NULL, connection_id UUID NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_synced_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_sales_sync_connection ON integration_sales_sync_cursors (connection_id)');
        $this->addSql('ALTER TABLE integration_sales_sync_cursors ADD CONSTRAINT FK_SALES_SYNC_CONNECTION FOREIGN KEY (connection_id) REFERENCES integration_connections (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE integration_sales_sync_cursors');
    }
}
