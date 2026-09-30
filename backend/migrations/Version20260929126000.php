<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929126000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserves non-product sales order lines without treating discounts or fees as stock-managed products.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sales_order_items ALTER sku DROP NOT NULL, ADD line_type VARCHAR(64) NOT NULL DEFAULT 'product', ADD source_payload JSON NOT NULL DEFAULT '{}'::json");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE sales_order_items SET sku = CONCAT('legacy-line-', external_line_id) WHERE sku IS NULL");
        $this->addSql('ALTER TABLE sales_order_items ALTER sku SET NOT NULL, DROP line_type, DROP source_payload');
    }
}
