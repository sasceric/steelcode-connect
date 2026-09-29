<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728080000 extends AbstractMigration
{
    public function getDescription(): string { return 'Stores the regular product price directly on products, separate from advanced price rules.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD regular_currency_code VARCHAR(3) DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD regular_tax_rate NUMERIC(5, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD regular_gross_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD regular_net_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD purchase_gross_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD purchase_net_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD list_gross_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD list_net_amount INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP regular_currency_code, DROP regular_tax_rate, DROP regular_gross_amount, DROP regular_net_amount, DROP purchase_gross_amount, DROP purchase_net_amount, DROP list_gross_amount, DROP list_net_amount');
    }
}
