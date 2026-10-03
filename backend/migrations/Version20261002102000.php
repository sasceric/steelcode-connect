<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index tenant-scoped reverse identity lookups so publication does not scan all imported mappings.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX CONCURRENTLY idx_integration_mapping_local_identity ON integration_entity_mappings (tenant_id, connection_id, entity_type, local_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX CONCURRENTLY idx_integration_mapping_local_identity');
    }
}
