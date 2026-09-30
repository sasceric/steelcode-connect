<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Protects physical return audit records from order deletion.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales_returns DROP CONSTRAINT fk_sales_return_order');
        $this->addSql('ALTER TABLE sales_returns ADD CONSTRAINT fk_sales_return_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales_returns DROP CONSTRAINT fk_sales_return_order');
        $this->addSql('ALTER TABLE sales_returns ADD CONSTRAINT fk_sales_return_order FOREIGN KEY (sales_order_id) REFERENCES sales_orders (id) ON DELETE CASCADE');
    }
}
