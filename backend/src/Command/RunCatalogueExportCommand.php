<?php

namespace App\Command;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Integration\CatalogueExportReferences;
use App\Integration\CatalogueExportSettings;
use App\Message\ExportShopwareCatalogue;
use App\MessageHandler\ExportShopwareCatalogueHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:catalogue-export:preview',
    description: 'Prepare a read-only Shopware preview using the normal handler and saved tenant-scoped settings.',
)]
final class RunCatalogueExportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly CatalogueExportReferences $references,
        private readonly ExportShopwareCatalogueHandler $handler,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tenant-id', InputArgument::REQUIRED);
        $this->addArgument('connection-id', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantId = (string) $input->getArgument('tenant-id');
        $connectionId = (string) $input->getArgument('connection-id');
        if (!Uuid::isValid($tenantId) || !Uuid::isValid($connectionId)) {
            return Command::INVALID;
        }
        $connection = $this->manager->getRepository(IntegrationConnection::class)->findOneBy([
            'tenant' => Uuid::fromString($tenantId),
            'id' => Uuid::fromString($connectionId),
            'connectorKey' => 'shopware',
        ]);
        if (!$connection instanceof IntegrationConnection || !$connection->isEnabled()) {
            $output->writeln('<error>Active Shopware connection not found in this tenant.</error>');

            return Command::FAILURE;
        }
        $settings = CatalogueExportSettings::normalize($connection->getConfiguration()['exportSettings'] ?? []);
        $this->references->validateLocalSettings($connection, $this->manager, $settings);
        if ($settings['salesChannelId'] === '' || $this->manager->getRepository(IntegrationImportRun::class)->findOneBy([
            'tenant' => $connection->getTenant(), 'connection' => $connection, 'status' => ['queued', 'running'],
        ]) instanceof IntegrationImportRun) {
            $output->writeln('<error>Choose a destination channel and wait for active integration runs.</error>');

            return Command::FAILURE;
        }
        $run = new IntegrationImportRun($connection->getTenant(), $connection, 'export_preview');
        $this->manager->persist($run);
        $this->manager->flush();
        $planId = (string) Uuid::v7();
        $runId = (string) $run->getId();
        $this->manager->getConnection()->insert('integration_export_plans', [
            'id' => $planId, 'tenant_id' => $tenantId, 'connection_id' => $connectionId,
            'run_id' => $runId, 'settings' => json_encode($settings, JSON_THROW_ON_ERROR),
            'settings_hash' => CatalogueExportSettings::hash($settings),
        ]);
        ($this->handler)(new ExportShopwareCatalogue($tenantId, $connectionId, $planId, $runId, true));
        $run = $this->manager->find(IntegrationImportRun::class, Uuid::fromString($runId));
        $output->writeln(sprintf('Preview %s: %s, %d products, %d blocked. No Shopware writes.', $planId, $run->getStatus(), $run->getProcessedItems(), $run->getFailedItems()));
        if ($run->getFailureReason()) {
            $output->writeln($run->getFailureReason());
        }
        if ($run->getStatus() === 'running') {
            $output->writeln('Preview continues through the bulk queue; run composer imports:consume.');
        }

        return in_array($run->getStatus(), ['running', 'completed'], true) ? Command::SUCCESS : Command::FAILURE;
    }
}
