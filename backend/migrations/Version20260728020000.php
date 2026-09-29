<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260728020000 extends AbstractMigration {
 public function getDescription(): string { return 'Adds global locales and product translations.'; }
 public function up(Schema $schema): void {
  $this->addSql('CREATE TABLE locales (id UUID NOT NULL, code VARCHAR(10) NOT NULL, name VARCHAR(100) NOT NULL, native_name VARCHAR(100) NOT NULL, direction VARCHAR(3) NOT NULL, active BOOLEAN NOT NULL, PRIMARY KEY(id))');
  $this->addSql('CREATE UNIQUE INDEX uniq_locale_code ON locales (code)');
  $this->addSql("INSERT INTO locales (id, code, name, native_name, direction, active) SELECT (substr(md5(code),1,8)||'-'||substr(md5(code),9,4)||'-4'||substr(md5(code),14,3)||'-8'||substr(md5(code),18,3)||'-'||substr(md5(code),21,12))::uuid, code, name, native_name, 'ltr', true FROM (VALUES ('sq-AL','Albanian','Shqip'),('bs-BA','Bosnian','Bosanski'),('bg-BG','Bulgarian','Български'),('hr-HR','Croatian','Hrvatski'),('cs-CZ','Czech','Čeština'),('da-DK','Danish','Dansk'),('nl-NL','Dutch','Nederlands'),('en-GB','English','English'),('et-EE','Estonian','Eesti'),('fi-FI','Finnish','Suomi'),('fr-FR','French','Français'),('de-DE','German','Deutsch'),('el-GR','Greek','Ελληνικά'),('hu-HU','Hungarian','Magyar'),('is-IS','Icelandic','Íslenska'),('ga-IE','Irish','Gaeilge'),('it-IT','Italian','Italiano'),('lv-LV','Latvian','Latviešu'),('lt-LT','Lithuanian','Lietuvių'),('mk-MK','Macedonian','Македонски'),('mt-MT','Maltese','Malti'),('nb-NO','Norwegian','Norsk bokmål'),('pl-PL','Polish','Polski'),('pt-PT','Portuguese','Português'),('ro-RO','Romanian','Română'),('ru-RU','Russian','Русский'),('sr-Latn-RS','Serbian Latin','Srpski'),('sr-Cyrl-RS','Serbian Cyrillic','Српски'),('sk-SK','Slovak','Slovenčina'),('sl-SI','Slovenian','Slovenščina'),('es-ES','Spanish','Español'),('sv-SE','Swedish','Svenska'),('tr-TR','Turkish','Türkçe'),('uk-UA','Ukrainian','Українська')) AS seed(code,name,native_name)");
  $this->addSql('CREATE TABLE product_translations (id UUID NOT NULL, product_id UUID NOT NULL, locale_id UUID NOT NULL, name VARCHAR(255) NOT NULL, short_description TEXT DEFAULT NULL, description TEXT DEFAULT NULL, meta_title VARCHAR(255) DEFAULT NULL, meta_description TEXT DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
  $this->addSql('CREATE UNIQUE INDEX uniq_product_translation_locale ON product_translations (product_id, locale_id)');
  $this->addSql('ALTER TABLE product_translations ADD CONSTRAINT fk_product_translation_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
  $this->addSql('ALTER TABLE product_translations ADD CONSTRAINT fk_product_translation_locale FOREIGN KEY (locale_id) REFERENCES locales (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
 }
 public function down(Schema $schema): void { $this->addSql('DROP TABLE product_translations'); $this->addSql('DROP TABLE locales'); }
}
