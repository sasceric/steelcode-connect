<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728070000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds Shopware-compatible product delivery, labelling, dimensions and discovery fields.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD min_purchase_quantity NUMERIC(19, 4) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE products ADD purchase_steps NUMERIC(19, 4) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE products ADD max_purchase_quantity NUMERIC(19, 4) DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD restock_time_days INT DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD clearance_sale BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE products ADD free_shipping BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE products ADD search_keywords TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP min_purchase_quantity, DROP purchase_steps, DROP max_purchase_quantity, DROP restock_time_days, DROP clearance_sale, DROP free_shipping, DROP search_keywords');
    }
}
