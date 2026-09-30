<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a tenant-scoped sequence for generated product numbers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants ADD next_product_number INT NOT NULL DEFAULT 10000');
        $this->addSql(
            "UPDATE tenants tenant
             SET next_product_number = GREATEST(
                 10000,
                 COALESCE(
                     (
                         SELECT MAX(product.sku::INTEGER) + 1
                         FROM products product
                         WHERE product.tenant_id = tenant.id
                           AND product.parent_id IS NULL
                           AND product.sku ~ '^[0-9]+$'
                     ),
                     10000
                 )
             )",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants DROP next_product_number');
    }
}
