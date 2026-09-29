<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Removes the legacy product tax rate column in favour of the tax relation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP tax_rate');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD tax_rate NUMERIC(5, 2) DEFAULT NULL');
    }
}
