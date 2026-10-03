<?php

namespace App\Tests\Unit\Integration;

use App\Integration\CatalogueExportSettings;
use App\MessageHandler\ExportShopwareCatalogueHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CatalogueExportSettingsTest extends TestCase
{
    public function testSelectionIsExplicitDeduplicatedAndHashIsOrderIndependent(): void
    {
        $id = (string) Uuid::v7();
        $settings = CatalogueExportSettings::normalize(['productIds' => [$id, $id]]);
        self::assertSame([$id], $settings['productIds']);
        self::assertSame([], CatalogueExportSettings::defaults()['productIds']);
        self::assertSame('keep', $settings['publicationMode']);
        self::assertSame('selected', $settings['scope']);
        self::assertFalse($settings['automaticSync']);
        self::assertFalse($settings['createMissingReferences']);
        self::assertNotSame(CatalogueExportSettings::hash([]), CatalogueExportSettings::hash(['scope' => 'all']));
        self::assertSame(CatalogueExportSettings::hash(['fields' => ['content', 'prices']]), CatalogueExportSettings::hash(['fields' => ['prices', 'content']]));
    }

    public function testUnsafeMappingAndArbitraryFieldGroupsAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CatalogueExportSettings::normalize(['fields' => ['stock', 'password']]);
    }

    public function testPriceAdjustmentDoesNotAllowNegativePrices(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CatalogueExportSettings::normalize(['priceMarkup' => '-101']);
    }

    public function testDestinationGuardIgnoresStockButDetectsContentAndTranslationEdits(): void
    {
        $payload = ['id' => str_repeat('a', 32), 'productNumber' => 'SKU', 'name' => 'Name', 'translations' => ['language' => ['name' => 'Name']]];
        $target = $payload + ['stock' => 12, 'updatedAt' => 'before'];
        $before = ExportShopwareCatalogueHandler::targetHash($target, $payload);
        $target['stock'] = 4;
        $target['updatedAt'] = 'after';
        self::assertSame($before, ExportShopwareCatalogueHandler::targetHash($target, $payload));
        $target['translations']['language']['name'] = 'Shop edit';
        self::assertNotSame($before, ExportShopwareCatalogueHandler::targetHash($target, $payload));
    }

    public function testDestinationGuardDetectsSelectedChannelVisibilityAndListPriceEdits(): void
    {
        $payload = [
            'visibilities' => [['salesChannelId' => 'selected', 'visibility' => 30]],
            'price' => [[
                'currencyId' => 'currency', 'gross' => 12, 'net' => 10,
                'listPrice' => ['gross' => 24, 'net' => 20],
            ]],
        ];
        $target = $payload;
        $before = ExportShopwareCatalogueHandler::targetHash($target, $payload);
        $target['visibilities'][] = ['salesChannelId' => 'unrelated', 'visibility' => 10];
        self::assertSame($before, ExportShopwareCatalogueHandler::targetHash($target, $payload));
        $target['visibilities'][0]['visibility'] = 10;
        self::assertNotSame($before, ExportShopwareCatalogueHandler::targetHash($target, $payload));
        $target = $payload;
        $target['price'][0]['listPrice']['gross'] = 25;
        self::assertNotSame($before, ExportShopwareCatalogueHandler::targetHash($target, $payload));
    }
}
