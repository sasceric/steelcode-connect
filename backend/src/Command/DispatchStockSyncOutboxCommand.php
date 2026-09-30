<?php

namespace App\Command;

use App\Entity\InventorySyncOutbox;
use App\Service\StockSyncOutboxDispatcher;
use App\Service\StockSyncNotReadyException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:stock-sync:dispatch',
    description: 'Dispatches pending aggregate inventory stock updates to sales channels.',
)]
final class DispatchStockSyncOutboxCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StockSyncOutboxDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum events to process.', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, min(1000, (int) $input->getOption('limit')));
        $events = $this->entityManager->getRepository(InventorySyncOutbox::class)->findBy(
            ['status' => ['pending', 'failed']],
            ['updatedAt' => 'ASC'],
            $limit,
        );
        $eventIds = array_map(
            static fn (InventorySyncOutbox $event) => $event->getId(),
            $events,
        );
        $dispatched = 0;
        $failed = 0;
        $waiting = 0;

        foreach ($eventIds as $eventId) {
            $event = $this->entityManager->find(InventorySyncOutbox::class, $eventId);
            if (!$event instanceof InventorySyncOutbox) {
                continue;
            }
            $connection = $this->entityManager->getConnection();
            $connection->beginTransaction();
            try {
                $this->entityManager->lock($event, LockMode::PESSIMISTIC_WRITE);
                $this->entityManager->refresh($event);
                if (!in_array($event->getStatus(), ['pending', 'failed'], true)) {
                    $connection->commit();
                    continue;
                }

                $this->dispatcher->dispatch($event, $this->entityManager);
                $this->entityManager->flush();
                $connection->commit();
                ++$dispatched;
            } catch (StockSyncNotReadyException) {
                $connection->rollBack();
                $this->entityManager->clear();
                ++$waiting;
            } catch (\Throwable $exception) {
                $connection->rollBack();
                $this->entityManager->clear();

                $failedEvent = $this->entityManager->find(InventorySyncOutbox::class, $eventId);
                if ($failedEvent instanceof InventorySyncOutbox) {
                    $failedEvent->markFailed($exception->getMessage());
                    $this->entityManager->flush();
                }
                ++$failed;
                $output->writeln(sprintf('<error>%s</error>', $exception->getMessage()));
            }
        }

        $output->writeln(sprintf(
            'Dispatched %d stock event(s); %d waiting for Sales sync; %d failed.',
            $dispatched,
            $waiting,
            $failed,
        ));

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
