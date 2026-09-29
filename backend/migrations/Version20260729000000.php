<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729000000 extends AbstractMigration
{
    public function getDescription(): string { return 'Adds file metadata to product media.'; }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE product_media ADD file_name VARCHAR(255) NOT NULL DEFAULT ''");
        $this->addSql('ALTER TABLE product_media ADD file_extension VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE product_media ADD file_size INT DEFAULT NULL');
        $this->addSql('ALTER TABLE product_media ADD mime_type VARCHAR(127) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_media DROP file_name, DROP file_extension, DROP file_size, DROP mime_type');
    }
}
