<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track catalogue assignment ownership and per-product attribute configuration.';
    }

    public function up(Schema $schema): void
    {
        foreach (['category_products', 'product_tags', 'product_property_assignments', 'product_media', 'product_downloads'] as $table) {
            // Historical assignments have no reliable provenance. Protect them
            // as manual instead of guessing and deleting another user's data.
            $this->addSql("ALTER TABLE $table ADD source_keys JSON NOT NULL DEFAULT '[\"manual\"]'");
        }
        $this->addSql("ALTER TABLE products ADD attribute_configuration JSON NOT NULL DEFAULT '[]'");
    }

    public function down(Schema $schema): void
    {
        foreach (['category_products', 'product_tags', 'product_property_assignments', 'product_media', 'product_downloads'] as $table) {
            $this->addSql("ALTER TABLE $table DROP source_keys");
        }
        $this->addSql('ALTER TABLE products DROP attribute_configuration');
    }
}
