<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Links products to canonical taxes, units, and delivery times.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD tax_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD unit_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD purchase_unit NUMERIC(19, 4) DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD reference_unit NUMERIC(19, 4) DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD delivery_time_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT fk_product_tax FOREIGN KEY (tax_id) REFERENCES taxes (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT fk_product_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT fk_product_delivery_time FOREIGN KEY (delivery_time_id) REFERENCES delivery_times (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("INSERT INTO taxes (id, tenant_id, name, rate, active) SELECT md5(random()::text || clock_timestamp()::text)::uuid, tenant_id, CONCAT('PDV ', trim(to_char(tax_rate, 'FM999990.00')), '%'), tax_rate, TRUE FROM products WHERE tax_rate IS NOT NULL GROUP BY tenant_id, tax_rate");
        $this->addSql('UPDATE products SET tax_id = taxes.id FROM taxes WHERE taxes.tenant_id = products.tenant_id AND taxes.rate = products.tax_rate');
        $this->addSql("INSERT INTO delivery_times (id, tenant_id, labels, min, max, unit, active) SELECT md5(random()::text || clock_timestamp()::text)::uuid, tenant_id, json_build_object('bs-BA', delivery_time), 0, 0, 'day', TRUE FROM products WHERE delivery_time IS NOT NULL AND delivery_time <> '' GROUP BY tenant_id, delivery_time");
        $this->addSql("UPDATE products SET delivery_time_id = delivery_times.id FROM delivery_times WHERE delivery_times.tenant_id = products.tenant_id AND delivery_times.labels->>'bs-BA' = products.delivery_time");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP CONSTRAINT fk_product_tax');
        $this->addSql('ALTER TABLE products DROP CONSTRAINT fk_product_unit');
        $this->addSql('ALTER TABLE products DROP CONSTRAINT fk_product_delivery_time');
        $this->addSql('ALTER TABLE products DROP tax_id, DROP unit_id, DROP purchase_unit, DROP reference_unit, DROP delivery_time_id');
    }
}
