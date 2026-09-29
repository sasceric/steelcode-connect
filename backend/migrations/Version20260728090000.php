<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260728090000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds cheapest-price fields to advanced product price rules.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE product_prices ADD cheapest_net_amount INT DEFAULT NULL'); $this->addSql('ALTER TABLE product_prices ADD cheapest_gross_amount INT DEFAULT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE product_prices DROP cheapest_net_amount, DROP cheapest_gross_amount'); }
}
