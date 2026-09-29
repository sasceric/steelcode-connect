<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260725010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds account profile fields to users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD first_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD last_name VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD phone VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD title VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD active BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE users ADD avatar_id UUID DEFAULT NULL');
        $this->addSql("ALTER TABLE users ADD locale VARCHAR(10) NOT NULL DEFAULT 'en'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP first_name');
        $this->addSql('ALTER TABLE users DROP last_name');
        $this->addSql('ALTER TABLE users DROP phone');
        $this->addSql('ALTER TABLE users DROP title');
        $this->addSql('ALTER TABLE users DROP active');
        $this->addSql('ALTER TABLE users DROP avatar_id');
        $this->addSql('ALTER TABLE users DROP locale');
    }
}
