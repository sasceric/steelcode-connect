<?php

namespace App\Command;

use App\Entity\Currency;
use App\Entity\IntegrationConnection;
use App\Entity\Product;
use App\Entity\ProductTranslation;
use App\Entity\ProductMedia;
use App\Entity\Media;
use App\Integration\CatalogueExportReferences;
use App\Integration\CatalogueMediaDelivery;
use App\Integration\WooCommerceCataloguePayload;
use App\Integration\WooCommerceClient;
use App\MessageHandler\ExportWooCommerceCatalogueHandler;
use App\Service\CatalogueSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'app:woo-publication:check', description: 'Inspect tenant-scoped Woo publication, with explicit opt-in mutation of QA-WOO demo fixtures only.')]
final class WooCommercePublicationCheckCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly CatalogueExportReferences $references,
        private readonly WooCommerceCataloguePayload $builder,
        private readonly WooCommerceClient $client,
        private readonly CatalogueSyncService $sync,
        private readonly CatalogueMediaDelivery $mediaDelivery,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tenant-id', InputArgument::REQUIRED);
        $this->addArgument('connection-id', InputArgument::REQUIRED);
        $this->addArgument('product-id', InputArgument::REQUIRED);
        $this->addOption('fix-demo-price', null, InputOption::VALUE_NONE);
        $this->addOption('fix-demo-media', null, InputOption::VALUE_NONE);
        $this->addOption('demo-name', null, InputOption::VALUE_REQUIRED);
        $this->addOption('enable-demo-auto', null, InputOption::VALUE_NONE);
        $this->addOption('sync-demo', null, InputOption::VALUE_NONE);
        $this->addOption('check-media', null, InputOption::VALUE_NONE);
        $this->addOption('check-baseline', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (['tenant-id', 'connection-id', 'product-id'] as $argument) {
            if (!Uuid::isValid((string) $input->getArgument($argument))) {
                return Command::INVALID;
            }
        }
        $connection = $this->manager->getRepository(IntegrationConnection::class)->findOneBy([
            'tenant' => $input->getArgument('tenant-id'), 'id' => $input->getArgument('connection-id'), 'connectorKey' => 'woocommerce',
        ]);
        $product = $this->manager->getRepository(Product::class)->findOneBy(['tenant' => $input->getArgument('tenant-id'), 'id' => $input->getArgument('product-id')]);
        if (!$connection instanceof IntegrationConnection || !$product instanceof Product) {
            return Command::FAILURE;
        }
        $settings = $connection->getConfiguration()['exportSettings'] ?? [];
        if ($input->getOption('check-media')) {
            $image = $this->manager->getRepository(ProductMedia::class)->findOneBy(['tenant' => $connection->getTenant(), 'product' => $product]);
            if (!$image instanceof ProductMedia) {
                throw new \DomainException('No product image assigned.');
            }
            $response = \Symfony\Component\HttpClient\HttpClient::create()->request('GET', $this->mediaDelivery->url($connection, $image->getMedia()));
            $output->writeln(json_encode(['mediaHttpStatus' => $response->getStatusCode(), 'contentType' => $response->getHeaders(false)['content-type'] ?? []], JSON_THROW_ON_ERROR));
            if ($response->getStatusCode() !== 200) {
                $content = $response->getContent(false);
                preg_match('#<h1[^>]*>(.*?)</h1>|<title>(.*?)</title>#s', $content, $matches);
                $output->writeln(strip_tags($matches[1] ?? $matches[2] ?? substr($content, 0, 400)));
            }

            return Command::SUCCESS;
        }
        $effective = $this->references->standardMappings($connection, $this->manager, $settings);
        $code = $effective['_woo']['woocommerce_currency'];
        $mutate = $input->getOption('fix-demo-price') || $input->getOption('fix-demo-media') || $input->getOption('demo-name') !== null
            || $input->getOption('enable-demo-auto') || $input->getOption('sync-demo');
        if ($mutate && (!str_starts_with($product->getSku() ?? '', 'QA-WOO-') || ($settings['scope'] ?? '') !== 'selected')) {
            throw new \DomainException('Only explicitly selected QA-WOO demo fixtures may be changed.');
        }
        if ($input->getOption('enable-demo-auto')) {
            $configuration = $connection->getConfiguration();
            $configuration['exportSettings']['automaticSync'] = true;
            $connection->updateConfiguration($configuration);
        }
        if ($input->getOption('fix-demo-price')) {
            $currency = $this->manager->getRepository(Currency::class)->findOneBy(['code' => $code]);
            if (!$currency instanceof Currency || !$this->references->owns($connection, $this->manager, 'currency', (string) $currency->getId())) {
                throw new \DomainException('The destination currency is not enabled in this tenant.');
            }
            $price = $product->getPrice();
            $price[(string) $currency->getId()] = ['currencyId' => (string) $currency->getId(), 'currencyCode' => $code, 'gross' => 12, 'net' => 10, 'linked' => true];
            $product->updatePrices($price, $product->getPurchasePrice(), $product->getCheapestPrice());
        }
        if ($input->getOption('fix-demo-media')) {
            $media = $this->manager->createQueryBuilder()->select('media')->from(Media::class, 'media')
                ->where('media.tenant = :tenant')->andWhere('media.mimeType IN (:types)')->andWhere('media.checksum IS NOT NULL')
                ->setParameter('tenant', $connection->getTenant())->setParameter('types', ['image/png', 'image/jpeg', 'image/webp'])
                ->setMaxResults(1)->getQuery()->getOneOrNullResult();
            if (!$media instanceof Media) {
                throw new \DomainException('No stored demo image is available.');
            }
            foreach ($this->manager->getRepository(ProductMedia::class)->findBy(['tenant' => $connection->getTenant(), 'product' => $product]) as $image) {
                $image->replaceMedia($media);
            }
        }
        if ($input->getOption('demo-name') !== null) {
            foreach ($this->manager->getRepository(ProductTranslation::class)->findBy(['product' => $product]) as $translation) {
                $translation->update((string) $input->getOption('demo-name'), $translation->getShortDescription(), $translation->getDescription(), $translation->getMetaTitle(), $translation->getMetaDescription(), $translation->getMetaKeywords(), $translation->getCustomFields());
            }
        }
        $this->manager->flush();
        if ($input->getOption('sync-demo')) {
            $output->writeln(json_encode($this->sync->queue($connection), JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }
        $secrets = $this->references->credentials($connection, $this->manager);
        $target = $this->client->queued('GET', $connection->getConfiguration()['baseUrl'], $secrets, 'products', query: ['sku' => $product->getSku(), 'per_page' => 2, 'context' => 'edit'])['data'];
        if ($input->getOption('check-baseline') && count($target) === 1) {
            if ($product->getParent() !== null) {
                $parent = $this->builder->externalId($connection, $this->manager, 'product', (string) $product->getParent()->getId(), $settings);
                $target[0] = $this->client->queued('GET', $connection->getConfiguration()['baseUrl'], $secrets, 'products/'.$parent.'/variations/'.$target[0]['id'], query: ['context' => 'edit'])['data'];
            }
            $baseline = $this->manager->getConnection()->fetchAssociative(
                'SELECT target_hash, owned_payload FROM integration_catalogue_publications WHERE tenant_id = :tenant AND connection_id = :connection AND product_id = :product',
                ['tenant' => (string) $connection->getTenant()->getId(), 'connection' => (string) $connection->getId(), 'product' => (string) $product->getId()],
            );
            $owned = json_decode($baseline['owned_payload'] ?? '{}', true);
            $differences = [];
            foreach ($owned as $key => $value) {
                if (in_array($key, ['id', '__media'], true)) {
                    continue;
                }
                if (ExportWooCommerceCatalogueHandler::targetHash($target[0], [$key => $value]) !== ExportWooCommerceCatalogueHandler::targetHash($owned, [$key => $value])) {
                    $differences[] = $key;
                }
            }
            $output->writeln(json_encode([
                'baselineMatches' => $baseline && $baseline['target_hash'] === ExportWooCommerceCatalogueHandler::targetHash($target[0], $owned),
                'normalizedIntentDifferences' => $differences,
            ], JSON_THROW_ON_ERROR));

            return Command::SUCCESS;
        }
        $output->writeln(json_encode([
            'storeCurrency' => $code, 'productsFound' => count($target),
            'products' => array_map(static fn (array $item): array => [
                'id' => $item['id'], 'name' => $item['name'], 'sku' => $item['sku'], 'type' => $item['type'],
                'status' => $item['status'], 'price' => $item['price'], 'stock' => $item['stock_quantity'],
                'images' => count($item['images'] ?? []), 'categories' => $item['categories'] ?? [],
                'brands' => $item['brands'] ?? [], 'attributes' => $item['attributes'] ?? [],
                'metaKeys' => array_column($item['meta_data'] ?? [], 'key'),
            ], $target),
        ], JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
