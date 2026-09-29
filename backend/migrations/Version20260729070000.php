<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729070000 extends AbstractMigration
{
    public function getDescription(): string { return 'Replaces split product price columns with Shopware-compatible JSONB price collections.'; }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE products ADD price JSONB NOT NULL DEFAULT '{}'::jsonb");
        $this->addSql("ALTER TABLE products ADD purchase_price JSONB NOT NULL DEFAULT '{}'::jsonb");
        $this->addSql("ALTER TABLE products ADD cheapest_price JSONB NOT NULL DEFAULT '{}'::jsonb");
        $this->addSql("UPDATE products p SET price = jsonb_build_object(c.id::text, jsonb_strip_nulls(jsonb_build_object('currencyId', c.id::text, 'currencyCode', c.code, 'gross', p.regular_gross_amount / 100.0, 'net', p.regular_net_amount / 100.0, 'linked', true, 'listPrice', CASE WHEN p.list_gross_amount IS NOT NULL OR p.list_net_amount IS NOT NULL THEN jsonb_build_object('currencyId', c.id::text, 'currencyCode', c.code, 'gross', p.list_gross_amount / 100.0, 'net', p.list_net_amount / 100.0, 'linked', true) ELSE NULL END))) FROM currencies c WHERE c.code = p.regular_currency_code");
        $this->addSql("UPDATE products p SET purchase_price = jsonb_build_object(c.id::text, jsonb_strip_nulls(jsonb_build_object('currencyId', c.id::text, 'currencyCode', c.code, 'gross', p.purchase_gross_amount / 100.0, 'net', p.purchase_net_amount / 100.0, 'linked', true))) FROM currencies c WHERE c.code = p.regular_currency_code AND (p.purchase_gross_amount IS NOT NULL OR p.purchase_net_amount IS NOT NULL)");
        $this->addSql('ALTER TABLE products DROP regular_currency_code, DROP regular_tax_rate, DROP regular_gross_amount, DROP regular_net_amount, DROP purchase_gross_amount, DROP purchase_net_amount, DROP list_gross_amount, DROP list_net_amount');

        $this->addSql("ALTER TABLE product_prices ADD price JSONB NOT NULL DEFAULT '{}'::jsonb");
        $this->addSql("UPDATE product_prices pp SET price = jsonb_build_object(c.id::text, jsonb_strip_nulls(jsonb_build_object('currencyId', c.id::text, 'currencyCode', c.code, 'gross', pp.gross_amount / 100.0, 'net', pp.net_amount / 100.0, 'linked', true, 'listPrice', CASE WHEN pp.list_gross_amount IS NOT NULL OR pp.list_net_amount IS NOT NULL THEN jsonb_build_object('currencyId', c.id::text, 'currencyCode', c.code, 'gross', pp.list_gross_amount / 100.0, 'net', pp.list_net_amount / 100.0, 'linked', true) ELSE NULL END, 'regulationPrice', CASE WHEN pp.cheapest_gross_amount IS NOT NULL OR pp.cheapest_net_amount IS NOT NULL THEN jsonb_build_object('currencyId', c.id::text, 'currencyCode', c.code, 'gross', pp.cheapest_gross_amount / 100.0, 'net', pp.cheapest_net_amount / 100.0, 'linked', true) ELSE NULL END))) FROM currencies c WHERE c.id = pp.currency_id");
        $this->addSql('ALTER TABLE product_prices DROP net_amount, DROP gross_amount, DROP list_net_amount, DROP list_gross_amount, DROP cheapest_net_amount, DROP cheapest_gross_amount');
    }

    public function down(Schema $schema): void { throw new \RuntimeException('This data migration is intentionally irreversible.'); }
}
