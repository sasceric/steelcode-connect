<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sets Bosnian, German and UK English as the default tenant snippet languages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE tenants ALTER COLUMN enabled_snippet_locales SET DEFAULT '[\"bs-BA\", \"de-DE\", \"en-GB\"]'",
        );
        $this->addSql(
            "UPDATE tenants SET enabled_snippet_locales = '[\"bs-BA\", \"de-DE\", \"en-GB\"]'",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE tenants ALTER COLUMN enabled_snippet_locales SET DEFAULT '[\"en-GB\"]'",
        );
    }
}
