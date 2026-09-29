<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726020000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds monthly or annual subscription interval.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE tenants ADD subscription_interval VARCHAR(16) DEFAULT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE tenants DROP subscription_interval'); }
}
