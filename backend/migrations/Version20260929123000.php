<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserves historical order lines when their catalogue product cannot be matched.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales_order_items ALTER product_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales_order_items ALTER product_id SET NOT NULL');
    }
}
