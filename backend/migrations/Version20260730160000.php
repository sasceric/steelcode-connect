<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds tenant-specific enabled snippet locales.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE tenants ADD enabled_snippet_locales JSON NOT NULL DEFAULT '[\"en-GB\"]'",
        );
        $this->addSql(<<<'SQL'
            UPDATE tenants
            SET enabled_snippet_locales = (
                SELECT json_agg(code ORDER BY code)
                FROM locales
                WHERE active = true
            )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants DROP enabled_snippet_locales');
    }
}
