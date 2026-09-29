<?php

namespace App\MessageHandler;

use App\Billing\MonthlyInvoiceGenerator;
use App\Message\GenerateMonthlyInvoices;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GenerateMonthlyInvoicesHandler
{
    public function __construct(private MonthlyInvoiceGenerator $generator)
    {
    }
    public function __invoke(GenerateMonthlyInvoices $message): void
    {
        $this->generator->generate();
    }
}
