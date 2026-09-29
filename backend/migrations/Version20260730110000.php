<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds Shopware-compatible packaging unit fields to products.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD pack_unit VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD pack_unit_plural VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP pack_unit');
        $this->addSql('ALTER TABLE products DROP pack_unit_plural');
    }
}
