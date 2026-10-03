<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001100500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep safe catalogue defaults for rolling deployments with old worker processes.';
    }

    public function up(Schema $schema): void
    {
        foreach (['category_products', 'product_tags', 'product_property_assignments', 'product_media', 'product_downloads'] as $table) {
            $this->addSql("ALTER TABLE $table ALTER source_keys SET DEFAULT '[\"manual\"]'");
        }
        $this->addSql("ALTER TABLE products ALTER attribute_configuration SET DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        // Retain protective defaults while either application version can run.
    }
}
