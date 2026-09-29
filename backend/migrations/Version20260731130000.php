<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds supplier offer validity dates.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE supplier_offers ADD valid_from DATE DEFAULT NULL, ADD valid_until DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE supplier_offers ADD CONSTRAINT chk_supplier_offer_valid_dates CHECK (valid_from IS NULL OR valid_until IS NULL OR valid_from <= valid_until)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE supplier_offers DROP CONSTRAINT chk_supplier_offer_valid_dates');
        $this->addSql('ALTER TABLE supplier_offers DROP valid_from, DROP valid_until');
    }
}
