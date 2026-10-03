<?php

namespace App\Tests\Integration;

use App\Entity\Category;
use App\Entity\CategoryProduct;
use App\Entity\Currency;
use App\Entity\CustomField;
use App\Entity\CustomFieldSet;
use App\Entity\Customer;
use App\Entity\IntegrationConnection;
use App\Entity\InventoryMovement;
use App\Entity\Locale;
use App\Entity\Media;
use App\Entity\Product;
use App\Entity\ProductCrossSelling;
use App\Entity\ProductCrossSellingAssignment;
use App\Entity\ProductDownload;
use App\Entity\ProductMedia;
use App\Entity\ProductPrice;
use App\Entity\ProductPropertyAssignment;
use App\Entity\ProductTag;
use App\Entity\ProductTranslation;
use App\Entity\Property;
use App\Entity\Tag;
use App\Entity\Tenant;
use App\Integration\SecretCipher;
use App\Integration\ShopwareClient;
use App\Integration\ShopwareMediaImporter;
use App\Integration\WooCommerceCatalogueImporter;
use App\Integration\WooCommerceCustomFieldImporter;
use App\Integration\WooCommerceSalesRecordIngestor;
use App\Service\InventoryService;
use App\Service\ProductBrandService;
use App\Service\SeoUrlService;
use App\Service\ProductVariantConfigurationReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class WooCommerceCatalogueParityTest extends KernelTestCase
{
    private EntityManagerInterface $manager;
    private IntegrationConnection $connection;
    private WooCommerceCatalogueImporter $catalogue;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->manager = self::getContainer()->get('doctrine')->getManager();
        $this->manager->getConnection()->beginTransaction();
        if (!$this->manager->getRepository(Locale::class)->findOneBy(['code' => 'en-GB'])) {
            $this->manager->persist(new Locale('en-GB', 'English', 'English'));
        }
        if (!$this->manager->getRepository(Currency::class)->findOneBy(['code' => 'EUR'])) {
            $this->manager->persist(new Currency('EUR', '€', 2));
        }
        $tenant = new Tenant('Woo parity rollback fixture');
        $this->connection = new IntegrationConnection($tenant, 'woocommerce', 'Woo parity', ['source', 'channel']);
        $this->connection->activate('Test');
        $this->connection->updateConfiguration(['baseUrl' => 'https://woo.example.test']);
        $this->manager->persist($tenant);
        $this->manager->persist($this->connection);
        $this->manager->flush();
        $this->catalogue = self::getContainer()->get(WooCommerceCatalogueImporter::class);
    }

    protected function tearDown(): void
    {
        if ($this->manager->getConnection()->isTransactionActive()) {
            $this->manager->getConnection()->rollBack();
        }
        parent::tearDown();
    }

    private function import(array $source, array $areas = [], ?string $parent = null): Product
    {
        $this->catalogue->product($source, $this->connection, $this->manager, ['woocommerce_currency' => 'EUR'], $areas, $parent);
        $this->manager->flush();

        return $this->catalogue->mapped($this->connection, 'product', (string) $source['id'], Product::class, $this->manager);
    }

    public function testTermRenameFlagsDefaultsAndSourceReconciliationPreserveManualAssignments(): void
    {
        $this->catalogue->reference('property_group', ['id' => 3, 'name' => 'Color'], $this->connection, $this->manager);
        $this->catalogue->reference('category', ['id' => 4, 'name' => 'Woo category'], $this->connection, $this->manager);
        $this->catalogue->reference('tag', ['id' => 5, 'name' => 'Woo tag'], $this->connection, $this->manager);
        $this->manager->flush();
        self::assertTrue($this->catalogue->term('3', ['id' => 7, 'name' => 'Blue', 'slug' => 'blue'], $this->connection, $this->manager));
        $this->manager->flush();
        $term = $this->catalogue->mapped($this->connection, 'property', '3:term:7', Property::class, $this->manager);
        $termId = (string) $term->getId();
        $source = [
            'id' => 1,
            'sku' => 'PARITY-1',
            'name' => 'Parent',
            'categories' => [['id' => 4]],
            'tags' => [['id' => 5]],
            'attributes' => [['id' => 3, 'name' => 'Color', 'options' => ['Blue'], 'position' => 2, 'visible' => false, 'variation' => true]],
            'default_attributes' => [['id' => 3, 'option' => 'Blue']],
        ];
        $product = $this->import($source);
        $this->import($source);
        $tag = $this->catalogue->mapped($this->connection, 'tag', '5', Tag::class, $this->manager);
        $tagId = (string) $tag->getId();
        $this->catalogue->reference('tag', ['id' => 5, 'name' => 'Renamed Woo tag'], $this->connection, $this->manager);
        $this->manager->flush();
        self::assertSame('Renamed Woo tag', $tag->getName());
        self::assertSame($tagId, (string) $this->catalogue->mapped($this->connection, 'tag', '5', Tag::class, $this->manager)->getId());
        self::assertSame(1, $this->manager->getRepository(Tag::class)->count(['tenant' => $product->getTenant()]));
        self::assertSame(1, $this->manager->getRepository(Property::class)->count(['tenant' => $product->getTenant()]));
        $config = $product->getAttributeConfiguration()[(string) $this->connection->getId()][0];
        self::assertFalse($config['visible']);
        self::assertTrue($config['variation']);
        self::assertSame(2, $config['position']);
        self::assertSame($termId, $config['defaultPropertyId']);
        self::assertSame([$termId], $config['propertyIds']);
        self::assertFalse($this->catalogue->term('3', ['id' => 7, 'name' => 'Azure', 'slug' => 'azure-code'], $this->connection, $this->manager));
        $this->manager->flush();
        self::assertSame('Azure', $term->getName());
        self::assertSame($termId, (string) $this->catalogue->mapped($this->connection, 'property', '3:term:7', Property::class, $this->manager)->getId());
        $source['attributes'][0]['options'] = ['Azure'];
        $source['default_attributes'][0]['option'] = 'azure-code';
        $this->import($source);
        self::assertSame($termId, $product->getAttributeConfiguration()[(string) $this->connection->getId()][0]['defaultPropertyId']);
        $variant = $this->import(['id' => 2, 'name' => 'Variation', 'attributes' => [['id' => 3, 'name' => 'Color', 'option' => 'azure']]], [], '1');
        self::assertSame([$termId], array_values($variant->getOptionValues()));
        $wildcard = $this->import(['id' => 3, 'name' => 'Any color', 'attributes' => [['id' => 3, 'name' => 'Color', 'option' => '']]], [], '1');
        self::assertSame(['*'], array_values($wildcard->getOptionValues()));
        $configuration = self::getContainer()->get(ProductVariantConfigurationReader::class)->read($product, $this->manager);
        $wildcardRows = array_values(array_filter($configuration['existingCombinations'], static fn(array $row): bool => $row['wildcardGroupIds'] !== []));
        self::assertCount(1, $wildcardRows);
        self::assertSame([(string) $term->getPropertyGroup()->getId()], $wildcardRows[0]['wildcardGroupIds']);
        self::assertSame(1, $this->manager->getRepository(Property::class)->count(['tenant' => $product->getTenant()]));

        $manualCategory = new Category($product->getTenant(), 0);
        $manualTag = new Tag($product->getTenant(), 'Manual tag');
        $this->manager->persist($manualCategory);
        $this->manager->persist($manualTag);
        $this->manager->persist(new CategoryProduct($manualCategory, $product, 0));
        $this->manager->persist(new ProductTag($product, $manualTag));
        $propertyAssignment = $this->manager->getRepository(ProductPropertyAssignment::class)->findOneBy(['product' => $product]);
        $propertyAssignment->claimSource('manual');
        $this->manager->flush();
        $source['attributes'] = [];
        $source['categories'] = [];
        $source['tags'] = [];
        $this->import($source, ['categories' => false]);
        self::assertSame(2, $this->manager->getRepository(CategoryProduct::class)->count(['product' => $product]));
        $this->import($source);
        self::assertSame(1, $this->manager->getRepository(CategoryProduct::class)->count(['product' => $product]));
        self::assertSame(1, $this->manager->getRepository(ProductTag::class)->count(['product' => $product]));
        self::assertSame(['manual'], $propertyAssignment->getSourceKeys());
        self::assertSame(1, $this->manager->getRepository(ProductPropertyAssignment::class)->count(['product' => $product]));
    }

    public function testMetadataIsTypedGroupedIdempotentAndTenantScoped(): void
    {
        $metadata = [
            ['key' => 'rank', 'value' => 12],
            ['key' => 'featured', 'value' => true],
            ['key' => 'settings', 'value' => ['nested' => [1, 2], 'secret' => 'excluded']],
            ['key' => 'repeated', 'value' => 'first'],
            ['key' => 'repeated', 'value' => 'second'],
            ['key' => 'nullable', 'value' => null],
            ['key' => 'api_token', 'value' => 'excluded'],
        ];
        $source = ['id' => 1, 'name' => 'Metadata product', 'meta_data' => $metadata];
        $product = $this->import($source);
        $this->import($source);
        $tenant = $product->getTenant();
        self::assertSame(1, $this->manager->getRepository(CustomFieldSet::class)->count(['tenant' => $tenant]));
        self::assertSame(5, $this->manager->getRepository(CustomField::class)->count(['tenant' => $tenant]));
        $fields = $this->manager->getRepository(CustomField::class)->findBy(['tenant' => $tenant]);
        $byKey = [];
        foreach ($fields as $field) {
            $byKey[$field->getConfig()['originalKey']] = $field;
        }
        self::assertSame('number', $byKey['rank']->getType());
        self::assertSame('switch', $byKey['featured']->getType());
        self::assertSame('json', $byKey['settings']->getType());
        $translation = $this->manager->getRepository(ProductTranslation::class)->findOneBy(['product' => $product]);
        $values = $translation->getCustomFields();
        self::assertSame(12, $values[$byKey['rank']->getTechnicalName()]);
        self::assertSame(['nested' => [1, 2]], $values[$byKey['settings']->getTechnicalName()]);
        self::assertSame(['first', 'second'], $values[$byKey['repeated']->getTechnicalName()]);
        self::assertArrayHasKey($byKey['nullable']->getTechnicalName(), $values);
        $service = self::getContainer()->get(WooCommerceCustomFieldImporter::class);
        $customer = $service->values($this->connection, 'customer', $metadata, $this->manager);
        $order = $service->values($this->connection, 'order', $metadata, $this->manager);
        $this->manager->flush();
        self::assertCount(5, $customer);
        self::assertCount(5, $order);
        self::assertSame([], array_intersect_key($customer, $order));
        self::assertSame(1, $this->manager->getRepository(CustomFieldSet::class)->count(['tenant' => $tenant]));
        $otherTenant = new Tenant('Other metadata tenant');
        $other = new IntegrationConnection($otherTenant, 'woocommerce', 'Other source', ['source']);
        $this->manager->persist($otherTenant);
        $this->manager->persist($other);
        $this->manager->flush();
        $service->values($other, 'product', $metadata, $this->manager);
        $this->manager->flush();
        self::assertSame(1, $this->manager->getRepository(CustomFieldSet::class)->count(['tenant' => $otherTenant]));
        $values['manual_field'] = 'keep';
        $updated = $service->values($this->connection, 'product', [['key' => 'rank', 'value' => ['different' => 'shape']]], $this->manager, $values);
        $this->manager->flush();
        self::assertSame('json', $byKey['rank']->getType());
        self::assertSame('keep', $updated['manual_field']);
        self::assertArrayNotHasKey($byKey['featured']->getTechnicalName(), $updated);
        self::assertSame(15, $this->manager->getRepository(CustomField::class)->count(['tenant' => $tenant]));
    }

    public function testUnsupportedLocalFieldsAndInheritedVariationDimensionsArePreserved(): void
    {
        $source = [
            'id' => 1,
            'name' => 'Local fields',
            'weight' => '2',
            'dimensions' => ['length' => '10', 'width' => '5', 'height' => '3'],
            'sold_individually' => false,
        ];
        $product = $this->import($source);
        $releaseDate = new \DateTimeImmutable('2026-12-01T00:00:00+00:00');
        $product->updateCommerce('physical', 'LOCAL-MPN', null, 'Local delivery', $releaseDate, false, ['local-channel']);
        $product->updateFulfilment('2.0000', '2.0000', '12.0000', 7, true, true, 'Local keywords', 2000, 100, 50, 30);
        $translation = $this->manager->getRepository(ProductTranslation::class)->findOneBy(['product' => $product]);
        $translation->update(
            $translation->getName(),
            null,
            null,
            'Local SEO title',
            'Local SEO description',
            'local, keywords',
            $translation->getCustomFields(),
        );
        $this->manager->flush();
        $this->import($source);
        self::assertSame('LOCAL-MPN', $product->getManufacturerNumber());
        self::assertSame('Local delivery', $product->getDeliveryTime());
        self::assertSame($releaseDate, $product->getReleaseDate());
        self::assertSame(['local-channel'], $product->getVisibility());
        self::assertSame('2.0000', $product->getMinPurchaseQuantity());
        self::assertSame('2.0000', $product->getPurchaseSteps());
        self::assertSame('12.0000', $product->getMaxPurchaseQuantity());
        self::assertSame(7, $product->getRestockTimeDays());
        self::assertTrue($product->isClearanceSale());
        self::assertTrue($product->isFreeShipping());
        self::assertSame('Local keywords', $product->getSearchKeywords());
        self::assertSame('Local SEO title', $translation->getMetaTitle());
        self::assertSame('Local SEO description', $translation->getMetaDescription());
        self::assertSame('local, keywords', $translation->getMetaKeywords());

        $variation = $this->import([
            'id' => 2,
            'name' => 'Inherited dimensions',
            'weight' => '',
            'dimensions' => ['length' => '', 'width' => '', 'height' => ''],
        ], [], '1');
        self::assertSame(2000, $variation->getWeightGrams());
        self::assertSame(100, $variation->getLengthMillimeters());
        self::assertSame(50, $variation->getWidthMillimeters());
        self::assertSame(30, $variation->getHeightMillimeters());

        $source['sold_individually'] = true;
        $this->import($source);
        self::assertSame('1.0000', $product->getMaxPurchaseQuantity());
        $source['sold_individually'] = false;
        $this->import($source);
        self::assertNull($product->getMaxPurchaseQuantity());
    }

    public function testRelationsSaleSchedulesAndStockAreReplaySafe(): void
    {
        $source = [
            'id' => 1,
            'name' => 'Scheduled sale',
            'price' => '20.00',
            'regular_price' => '20.00',
            'sale_price' => '15.00',
            'date_on_sale_from_gmt' => '2026-12-01T10:00:00',
            'date_on_sale_to_gmt' => '2026-12-15T10:00:00',
            'manage_stock' => true,
            'stock_quantity' => 10,
            'cross_sell_ids' => [2, 2],
            'upsell_ids' => [3],
        ];
        $product = $this->import($source);
        $this->import(['id' => 2, 'name' => 'Related A']);
        $this->import(['id' => 3, 'name' => 'Related B']);
        $this->catalogue->relatedProducts($source, $this->connection, $this->manager);
        $this->manager->flush();
        $movements = $this->manager->getRepository(InventoryMovement::class)->count(['tenant' => $product->getTenant()]);
        $source['stock_quantity'] = 100;
        $this->import($source);
        $this->catalogue->relatedProducts($source, $this->connection, $this->manager);
        $this->manager->flush();
        self::assertSame($movements, $this->manager->getRepository(InventoryMovement::class)->count(['tenant' => $product->getTenant()]));
        self::assertSame(2, $this->manager->getRepository(ProductCrossSelling::class)->count(['product' => $product]));
        $schedule = $this->manager->getRepository(ProductPrice::class)->findOneBy(['product' => $product]);
        self::assertSame('2026-12-01T10:00:00+00:00', $schedule->getValidFrom()->format(DATE_ATOM));
        self::assertSame(15.0, $schedule->getPrice()[(string) $schedule->getCurrency()->getId()]['gross']);
        self::assertSame(1, $this->manager->getRepository(ProductPrice::class)->count(['product' => $product]));
        $manual = new ProductCrossSelling($product->getTenant(), $product, 'manual-fixture', 'Manual', 'productList', true, 2, null);
        $this->manager->persist($manual);
        $this->manager->flush();
        $source['cross_sell_ids'] = [];
        $source['upsell_ids'] = [2];
        $source['sale_price'] = '';
        $this->import($source);
        $this->catalogue->relatedProducts($source, $this->connection, $this->manager);
        $this->manager->flush();
        self::assertSame(2, $this->manager->getRepository(ProductCrossSelling::class)->count(['product' => $product]));
        self::assertSame(0, $this->manager->getRepository(ProductPrice::class)->count(['product' => $product]));
        self::assertSame(1, $this->manager->getRepository(ProductCrossSellingAssignment::class)->count([]));
    }

    public function testSalesMetadataUsesTheSameSetAndReconcilesLegacyRawKeys(): void
    {
        $records = self::getContainer()->get(WooCommerceSalesRecordIngestor::class);
        $product = $this->import(['id' => 1, 'name' => 'Sales item']);
        $source = ['id' => 9, 'email' => 'demo@example.test', 'meta_data' => [['key' => 'preferences', 'value' => ['a' => true]]]];
        $records->customer($source, $this->connection, $this->manager);
        $this->manager->flush();
        $customer = $this->manager->getRepository(Customer::class)->findOneBy(['connection' => $this->connection, 'externalId' => '9']);
        self::assertCount(1, $customer->getSourcePayload()['customFields']);
        $source['meta_data'] = [['key' => 'preferences', 'value' => ['a' => false]]];
        $records->customer($source, $this->connection, $this->manager);
        $this->manager->flush();
        self::assertSame([['a' => false]], array_values($customer->getSourcePayload()['customFields']));
        $orderSource = [
            'id' => 20,
            'customer_id' => 9,
            'status' => 'completed',
            'currency' => 'EUR',
            'total' => '10.00',
            'line_items' => [['id' => 1, 'product_id' => 1, 'name' => 'Sales item', 'quantity' => 1, 'total' => '10.00']],
            'meta_data' => [['key' => 'notes', 'value' => 'Imported note']],
        ];
        $order = $records->order($orderSource, $this->connection, $this->manager, true);
        $payload = $order->getSourcePayload();
        $payload['customFields'] = ['notes' => 'Imported note', 'manual_note' => 'Keep'];
        $order->updateSourcePayload($payload);
        $this->manager->flush();
        $order = $records->order($orderSource, $this->connection, $this->manager, true);
        self::assertArrayNotHasKey('notes', $order->getSourcePayload()['customFields']);
        self::assertSame('Keep', $order->getSourcePayload()['customFields']['manual_note']);
        self::assertCount(2, $order->getSourcePayload()['customFields']);
        self::assertSame(1, $this->manager->getRepository(CustomFieldSet::class)->count(['tenant' => $product->getTenant()]));
        self::assertSame(2, $this->manager->getRepository(CustomField::class)->count(['tenant' => $product->getTenant()]));
        $orderSource['meta_data'] = [];
        $order = $records->order($orderSource, $this->connection, $this->manager, true);
        self::assertSame(['manual_note' => 'Keep'], $order->getSourcePayload()['customFields']);
        self::assertSame(0, $this->manager->getRepository(InventoryMovement::class)->count(['tenant' => $product->getTenant()]));
    }

    public function testMediaAndDownloadScopesUpdatesAndRemoval(): void
    {
        $requests = 0;
        $http = new MockHttpClient(function (string $method, string $url) use (&$requests): MockResponse {
            ++$requests;
            $content = str_ends_with($url, '.png')
                ? base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
                : 'Safe plain-text download ' . $url;

            return new MockResponse($content, ['http_code' => 200]);
        });
        $media = new ShopwareMediaImporter(
            $this->manager,
            self::getContainer()->get(SecretCipher::class),
            self::getContainer()->get(ShopwareClient::class),
            $http,
            sys_get_temp_dir() . '/steelcode-woo-parity-' . bin2hex(random_bytes(8)),
        );
        $this->catalogue = new WooCommerceCatalogueImporter(
            self::getContainer()->get(InventoryService::class),
            $media,
            self::getContainer()->get(SeoUrlService::class),
            self::getContainer()->get(ProductBrandService::class),
            self::getContainer()->get(WooCommerceCustomFieldImporter::class),
        );
        $source = [
            'id' => 1,
            'name' => 'Download product',
            'images' => [['id' => 5, 'src' => 'https://woo.example.test/image.png', 'date_modified_gmt' => '2026-09-30T00:00:00']],
            'downloadable' => true,
            'downloads' => [['id' => 'download-id', 'name' => 'Manual', 'file' => 'https://woo.example.test/manual.txt']],
        ];
        $product = $this->import($source, ['media' => false, 'productDownloads' => false]);
        self::assertSame(0, $requests);
        $this->import($source);
        $this->import($source);
        self::assertSame(2, $requests);
        self::assertSame(1, $this->manager->getRepository(ProductMedia::class)->count(['product' => $product]));
        self::assertSame(1, $this->manager->getRepository(ProductDownload::class)->count(['product' => $product]));
        $source['images'][0]['date_modified_gmt'] = '2026-10-01T00:00:00';
        $source['downloads'][0]['file'] = 'https://woo.example.test/updated.txt';
        $this->import($source);
        self::assertSame(4, $requests);
        self::assertSame(3, $this->manager->getRepository(Media::class)->count(['tenant' => $product->getTenant()]));
        self::assertSame(1, $this->manager->getRepository(ProductDownload::class)->count(['product' => $product]));
        $image = $this->manager->getRepository(ProductMedia::class)->findOneBy(['product' => $product]);
        self::assertSame(0, $image->getSortOrder());
        $image->claimSource('manual');
        $source['images'] = [];
        $source['downloads'] = [];
        $this->import($source);
        self::assertSame(1, $this->manager->getRepository(ProductMedia::class)->count(['product' => $product]));
        self::assertSame(0, $this->manager->getRepository(ProductDownload::class)->count(['product' => $product]));
        self::assertSame(['manual'], $image->getSourceKeys());
    }
}
