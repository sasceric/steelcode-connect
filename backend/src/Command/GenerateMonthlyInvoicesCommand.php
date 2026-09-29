<?php

namespace App\Command;

use App\Billing\MonthlyInvoiceGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:invoices:generate',
    description: 'Creates due monthly and annual subscription invoices.',
    aliases: ['app:invoices:generate-monthly'],
)]
final class GenerateMonthlyInvoicesCommand extends Command
{
    public function __construct(private MonthlyInvoiceGenerator $generator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('date', null, InputOption::VALUE_REQUIRED, 'Billing date to process (YYYY-MM-DD).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $date = $input->getOption('date');
        $billingDate = null;

        if (is_string($date)) {
            $billingDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            $errors = \DateTimeImmutable::getLastErrors();

            if ($billingDate === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                $output->writeln('<error>Use a valid date in YYYY-MM-DD format.</error>');

                return Command::INVALID;
            }
        }

        $output->writeln(sprintf('Created %d due invoice(s).', $this->generator->generate($billingDate)));

        return Command::SUCCESS;
    }
}
