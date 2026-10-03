<?php

namespace App;

use App\Message\GenerateMonthlyInvoices;
use App\Message\QueueShopwareSalesSync;
use App\Message\QueueShopwareCatalogueSync;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run

            ->add(RecurringMessage::cron(
                '0 0 * * *',
                new RedispatchMessage(new GenerateMonthlyInvoices(), ['async']),
                'Europe/Sarajevo',
            ))
            ->add(RecurringMessage::every(
                '1 minute',
                new RedispatchMessage(new QueueShopwareSalesSync(), ['control']),
            ))
            ->add(RecurringMessage::every(
                '5 seconds',
                new RedispatchMessage(new QueueShopwareCatalogueSync(), ['control']),
            ))
            ->add(RecurringMessage::every(
                '1 minute',
                new RedispatchMessage(new RunCommandMessage('app:stock-sync:dispatch --limit=100', false), ['stock']),
            ));
    }
}
