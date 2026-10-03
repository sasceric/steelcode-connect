<?php

namespace App\Command;

use App\Service\IntegrationSyncHealth;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:catalogue-sync:status',
    description: 'Report Shopware/Woo sync health for one tenant and connection (read-only JSON).',
)]
final class CatalogueSyncStatusCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly IntegrationSyncHealth $health,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tenant-id', InputArgument::REQUIRED);
        $this->addArgument('connection-id', InputArgument::REQUIRED);
        $this->addOption('check', null, InputOption::VALUE_NONE, 'Exit 1 for critical, 2 for warning, 0 for healthy.');
        $this->addOption('backlog-seconds', null, InputOption::VALUE_REQUIRED, 'Pending/queued age warning threshold.', '120');
        $this->addOption('stale-run-seconds', null, InputOption::VALUE_REQUIRED, 'Running heartbeat age warning threshold.', '900');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantId = (string) $input->getArgument('tenant-id');
        $connectionId = (string) $input->getArgument('connection-id');
        $backlogSeconds = filter_var($input->getOption('backlog-seconds'), FILTER_VALIDATE_INT);
        $staleRunSeconds = filter_var($input->getOption('stale-run-seconds'), FILTER_VALIDATE_INT);
        if ($backlogSeconds === false || $staleRunSeconds === false) {
            return Command::INVALID;
        }
        try {
            $report = $this->health->read(
                $this->manager->getConnection(),
                $tenantId,
                $connectionId,
                $backlogSeconds,
                $staleRunSeconds,
            );
        } catch (\InvalidArgumentException $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');

            return Command::INVALID;
        } catch (\DomainException $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');

            return Command::FAILURE;
        }
        $output->writeln(json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        if ($input->getOption('check')) {
            return match ($report['health']) {
                'critical' => Command::FAILURE,
                'warning' => 2,
                default => Command::SUCCESS,
            };
        }

        return Command::SUCCESS;
    }
}
