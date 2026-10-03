<?php

namespace App\Command;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Message\ImportWooCommerce;
use App\MessageHandler\ImportWooCommerceHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:woocommerce:run-import',
    description: 'Run the normal scoped Woo import handler in a fresh process for maintenance/acceptance.',
)]
final class RunWooCommerceImportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly ImportWooCommerceHandler $handler,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('tenant-id', InputArgument::REQUIRED)
            ->addArgument('connection-id', InputArgument::REQUIRED)
            ->addArgument('type', InputArgument::REQUIRED, 'products or sales');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tenantId = (string) $input->getArgument('tenant-id');
        $connectionId = (string) $input->getArgument('connection-id');
        $type = (string) $input->getArgument('type');
        if (!Uuid::isValid($tenantId) || !Uuid::isValid($connectionId) || !in_array($type, ['products', 'sales'], true)) {
            $output->writeln('<error>Valid tenant/connection IDs and products or sales are required.</error>');

            return Command::INVALID;
        }
        $connection = $this->manager->getRepository(IntegrationConnection::class)->findOneBy([
            'id' => Uuid::fromString($connectionId),
            'tenant' => Uuid::fromString($tenantId),
            'connectorKey' => 'woocommerce',
        ]);
        if (!$connection instanceof IntegrationConnection || !$connection->isEnabled() || $connection->getStatus() !== 'active') {
            $output->writeln('<error>Active WooCommerce connection not found in this tenant.</error>');

            return Command::FAILURE;
        }
        $pending = $this->manager->createQueryBuilder()
            ->select('run.id')
            ->from(IntegrationImportRun::class, 'run')
            ->where('run.tenant = :tenant AND run.connection = :connection AND run.status IN (:statuses)')
            ->setParameter('tenant', $connection->getTenant())
            ->setParameter('connection', $connection)
            ->setParameter('statuses', ['queued', 'running'])
            ->setMaxResults(1)
            ->getQuery()
            ->getArrayResult();
        if ($pending !== []) {
            $output->writeln('<error>A queued/running import already exists. Use the normal worker for that run.</error>');

            return Command::FAILURE;
        }
        $run = new IntegrationImportRun($connection->getTenant(), $connection, $type);
        $this->manager->persist($run);
        $this->manager->flush();
        $id = (string) $run->getId();
        $output->writeln('Import run: ' . $id . ' (normal history/logs/cancellation and saved scopes).');
        ($this->handler)(new ImportWooCommerce($id));
        $run = $this->manager->getRepository(IntegrationImportRun::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => Uuid::fromString($tenantId),
        ]);
        $output->writeln(sprintf(
            '%s: %d created, %d updated, %d failed.',
            $run->getStatus(),
            $run->getCreatedItems(),
            $run->getUpdatedItems(),
            $run->getFailedItems(),
        ));

        return $run->getStatus() === 'completed' && $run->getFailedItems() === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
