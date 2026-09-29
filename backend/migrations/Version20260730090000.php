<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the tenant default locale for snippets and translated catalogue data.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tenants ADD default_snippet_locale VARCHAR(10) NOT NULL DEFAULT 'en-GB'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants DROP default_snippet_locale');
    }
}
