<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929128000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a round-robin cursor for retrying unallocated incoming orders.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integration_sales_sync_cursors ADD last_pending_order_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_sales_order_connection_status_id ON sales_orders (connection_id, status, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_sales_order_connection_status_id');
        $this->addSql('ALTER TABLE integration_sales_sync_cursors DROP last_pending_order_id');
    }
}
