<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index bounded catalogue preflight/publication work with parent-first ordering.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE INDEX idx_export_items_preflight_work ON integration_export_items (plan_id, parent_id ASC NULLS FIRST, product_id) WHERE preflight_checked = FALSE AND status <> 'published'");
        $this->addSql("CREATE INDEX idx_export_items_publication_work ON integration_export_items (plan_id, parent_id ASC NULLS FIRST, product_id) WHERE status = 'pending'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_export_items_preflight_work');
        $this->addSql('DROP INDEX idx_export_items_publication_work');
    }
}
