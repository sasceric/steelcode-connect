<?php

namespace App\Command;

use App\Entity\Brand;
use App\Entity\BrandTranslation;
use App\Entity\Category;
use App\Entity\CategoryProduct;
use App\Entity\CategoryTranslation;
use App\Entity\Currency;
use App\Entity\IntegrationConnection;
use App\Entity\Locale;
use App\Entity\Media;
use App\Entity\Product;
use App\Entity\ProductBrand;
use App\Entity\ProductMedia;
use App\Entity\ProductTranslation;
use App\Entity\Property;
use App\Entity\PropertyGroup;
use App\Integration\CatalogueExportSettings;
use App\Integration\CatalogueExportReferences;
use App\Service\CatalogueSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(name: 'app:woo-publication:acceptance-fixture', description: 'Create a clearly marked demo parent/variation and queue normal catalogue publication. Never run against a production shop.')]
final class WooCommercePublicationAcceptanceCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly CatalogueSyncService $sync,
        private readonly CatalogueExportReferences $references,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tenant-id', InputArgument::REQUIRED);
        $this->addArgument('connection-id', InputArgument::REQUIRED);
        $this->addOption('disposable-demo', null, InputOption::VALUE_NONE, 'Confirm this is a disposable demo shop. Replaces this connection export scope with only the new demo parent.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantId = (string) $input->getArgument('tenant-id');
        $connectionId = (string) $input->getArgument('connection-id');
        if (!$input->getOption('disposable-demo') || !Uuid::isValid($tenantId) || !Uuid::isValid($connectionId)) {
            return Command::INVALID;
        }
        $connection = $this->manager->getRepository(IntegrationConnection::class)->findOneBy(['tenant' => $tenantId, 'id' => $connectionId, 'connectorKey' => 'woocommerce']);
        if (!$connection instanceof IntegrationConnection) {
            return Command::FAILURE;
        }
        $tenant = $connection->getTenant();
        $locale = $this->manager->getRepository(Locale::class)->findOneBy(['code' => $tenant->getDefaultSnippetLocale()]);
        $store = $this->references->standardMappings($connection, $this->manager, []);
        $currency = $this->manager->getRepository(Currency::class)->findOneBy(['code' => $store['_woo']['woocommerce_currency']]);
        if (!$locale instanceof Locale || !$currency instanceof Currency) {
            return Command::FAILURE;
        }
        $suffix = substr(str_replace('-', '', (string) Uuid::v7()), -10);
        $parent = new Product($tenant);
        $parent->updateIdentity('QA-WOO-'.$suffix, null);
        $group = new PropertyGroup($tenant, 'Acceptance colour '.$suffix, 'qa-woo-'.$suffix, 'text', true, true, 'alphanumeric', 0);
        $property = new Property($tenant, $group, 'Blue', 'qa-blue-'.$suffix, null, 0);
        $child = new Product($tenant);
        $child->makeChildOf($parent, 'QA-WOO-'.$suffix.'-BLUE', null, [(string) $group->getId() => (string) $property->getId()]);
        $child->updatePrices([(string) $currency->getId() => ['currencyId' => (string) $currency->getId(), 'gross' => 12, 'net' => 10]], [], []);
        $parent->updateAttributeConfiguration('manual', [[
            'groupId' => (string) $group->getId(), 'propertyIds' => [(string) $property->getId()],
            'variation' => true, 'visible' => true, 'defaultPropertyId' => (string) $property->getId(),
        ]]);
        $translation = new ProductTranslation($parent, $locale, 'Connect Woo acceptance '.$suffix);
        $translation->update('Connect Woo acceptance '.$suffix, 'Demo short description', '<p>Disposable publication acceptance fixture.</p>', null, null, null, ['connect_demo_note' => 'Publication acceptance']);
        $childTranslation = new ProductTranslation($child, $locale, 'Connect Woo acceptance '.$suffix);
        $category = new Category($tenant, 0);
        $brand = new Brand($tenant);
        foreach ([
            $parent, $child, $group, $property, $translation, $childTranslation,
            $category, new CategoryTranslation($category, $locale, 'Acceptance catalogue '.$suffix),
            new CategoryProduct($category, $parent, 0),
            $brand, new BrandTranslation($brand, $locale, 'Acceptance brand '.$suffix),
            new ProductBrand($parent, $brand),
        ] as $entity) {
            $this->manager->persist($entity);
        }
        $media = $this->manager->createQueryBuilder()->select('media')->from(Media::class, 'media')
            ->where('media.tenant = :tenant')->andWhere('media.mimeType IN (:types)')
            ->andWhere('media.checksum IS NOT NULL')
            ->setParameter('tenant', $tenant)->setParameter('types', ['image/jpeg', 'image/png', 'image/webp'])
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        if ($media instanceof Media) {
            $this->manager->persist(new ProductMedia($tenant, $parent, $media, 0));
        }
        $configuration = $connection->getConfiguration();
        $configuration['exportSettings'] = CatalogueExportSettings::normalize([
            'productIds' => [(string) $parent->getId()],
            'destinationLocaleId' => (string) $locale->getId(),
            'createMissingReferences' => true, 'includeVariants' => true,
            'publicationMode' => 'activate',
            'fields' => ['content', 'prices', 'classification', 'fulfilment', 'customFields', 'media'],
        ], 'woocommerce');
        $connection->updateConfiguration($configuration);
        $this->manager->flush();
        $result = $this->sync->queue($connection);
        $output->writeln(json_encode(['parentId' => (string) $parent->getId(), 'variationId' => (string) $child->getId(), 'sku' => $parent->getSku(), ...$result], JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
