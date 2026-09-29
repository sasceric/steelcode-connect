<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allows only one active preferred supplier offer per product.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX uniq_preferred_supplier_offer ON supplier_offers (tenant_id, product_id) WHERE preferred = true AND active = true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_preferred_supplier_offer');
    }
}
