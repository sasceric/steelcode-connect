<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730010000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds reusable product tags and time to product release dates.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ALTER release_date TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('CREATE TABLE tags (id UUID NOT NULL, tenant_id UUID NOT NULL, name VARCHAR(100) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_tag_tenant_name ON tags (tenant_id, name)');
        $this->addSql('CREATE TABLE product_tags (id UUID NOT NULL, product_id UUID NOT NULL, tag_id UUID NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_product_tag ON product_tags (product_id, tag_id)');
        $this->addSql('ALTER TABLE tags ADD CONSTRAINT fk_tags_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_tags ADD CONSTRAINT fk_product_tags_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE product_tags ADD CONSTRAINT fk_product_tags_tag FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE product_tags'); $this->addSql('DROP TABLE tags'); $this->addSql('ALTER TABLE products ALTER release_date TYPE DATE'); }
}
