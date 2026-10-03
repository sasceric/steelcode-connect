<?php

namespace App\Command;

use App\Entity\IntegrationImportRun;
use App\Message\ExportShopwareCatalogue;
use App\Message\ExportWooCommerceCatalogue;
use App\MessageHandler\ExportShopwareCatalogueHandler;
use App\MessageHandler\ExportWooCommerceCatalogueHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:catalogue-export:run',
    description: 'Process one existing tenant-scoped export run with the normal handler, without restarting services.',
)]
final class RunQueuedCatalogueExportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly ExportShopwareCatalogueHandler $handler,
        private readonly ExportWooCommerceCatalogueHandler $wooHandler,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tenant-id', InputArgument::REQUIRED);
        $this->addArgument('run-id', InputArgument::REQUIRED);
        $this->addOption('allow-publish', null, InputOption::VALUE_NONE, 'Permit the already-confirmed publication run to write to its connected shop.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantId = (string) $input->getArgument('tenant-id');
        $runId = (string) $input->getArgument('run-id');
        if (!Uuid::isValid($tenantId) || !Uuid::isValid($runId)) {
            return Command::INVALID;
        }
        $plan = $this->manager->getConnection()->fetchAssociative(
            'SELECT * FROM integration_export_plans WHERE tenant_id = :tenant AND run_id = :run',
            ['tenant' => $tenantId, 'run' => $runId],
        );
        $run = $this->manager->getRepository(IntegrationImportRun::class)->findOneBy([
            'id' => Uuid::fromString($runId), 'tenant' => Uuid::fromString($tenantId),
        ]);
        if (!$plan || !$run instanceof IntegrationImportRun || !in_array($run->getType(), ['export', 'export_preview', 'export_sync'], true)) {
            $output->writeln('<error>Export run not found in this tenant.</error>');

            return Command::FAILURE;
        }
        $preview = in_array($run->getType(), ['export_preview', 'export_sync'], true);
        if ($run->getType() !== 'export_preview' && !$input->getOption('allow-publish')) {
            $output->writeln('<error>Publication requires --allow-publish.</error>');

            return Command::FAILURE;
        }
        $woo = $run->getConnection()->getConnectorKey() === 'woocommerce';
        $class = $woo ? ExportWooCommerceCatalogue::class : ExportShopwareCatalogue::class;
        ($woo ? $this->wooHandler : $this->handler)(new $class(
            $tenantId,
            $plan['connection_id'],
            $plan['id'],
            $runId,
            $preview,
            $plan['work_token'],
        ));
        $run = $this->manager->find(IntegrationImportRun::class, Uuid::fromString($runId));
        $output->writeln(sprintf('%s: %d processed, %d failed.', $run->getStatus(), $run->getProcessedItems(), $run->getFailedItems()));

        if (in_array($run->getStatus(), ['queued', 'running'], true)) {
            $output->writeln('This chunk yielded; consume the appropriate queue for its continuation.');
        }

        return in_array($run->getStatus(), ['queued', 'running', 'completed'], true)
            && $run->getFailedItems() === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
