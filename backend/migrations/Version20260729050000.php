<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729050000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renames the legacy property default-media relation when present.';
    }

    public function up(Schema $schema): void
    {
        // The preceding migration now creates media_id directly. Older databases
        // may still have the original default_media_id column at this point.
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'properties' AND column_name = 'default_media_id'
    ) AND NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'properties' AND column_name = 'media_id'
    ) THEN
        ALTER TABLE properties RENAME COLUMN default_media_id TO media_id;
    END IF;

    IF to_regclass('idx_properties_default_media') IS NOT NULL
        AND to_regclass('idx_properties_media') IS NULL THEN
        ALTER INDEX idx_properties_default_media RENAME TO idx_properties_media;
    END IF;

    IF EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'properties'::regclass
            AND conname = 'fk_properties_default_media'
    ) AND NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conrelid = 'properties'::regclass
            AND conname = 'fk_properties_media'
    ) THEN
        ALTER TABLE properties RENAME CONSTRAINT fk_properties_default_media TO fk_properties_media;
    END IF;
END
$$
SQL);
    }

    public function down(Schema $schema): void
    {
        // Version20260729040000 owns media_id in a fresh installation. Reverting
        // this compatibility step must not remove or rename that column.
    }
}
