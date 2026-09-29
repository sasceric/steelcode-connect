<?php

namespace App\Billing;

use App\Entity\Invoice;
use App\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;

final class MonthlyInvoiceGenerator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function generate(?\DateTimeImmutable $date = null): int
    {
        $period = ($date ?? new \DateTimeImmutable())->setTime(0, 0);
        $created = 0;
        $year = $period->format('Y');
        $sequence = $this->currentSequence($year);

        foreach ($this->entityManager->getRepository(Tenant::class)->findAll() as $tenant) {
            if (!$tenant instanceof Tenant || $tenant->getSubscriptionPlan() === null || $tenant->getSubscriptionPrice() === null) {
                continue;
            }
            $interval = $tenant->getSubscriptionInterval() ?? 'monthly';
            if ($interval === 'monthly' && $period->format('d') !== '01') {
                continue;
            }
            if ($this->entityManager->getRepository(Invoice::class)->findOneBy(['tenant' => $tenant, 'billingPeriod' => $period])) {
                continue;
            }

            if ($interval === 'annual') {
                $latestInvoice = $this->entityManager->getRepository(Invoice::class)->findOneBy(['tenant' => $tenant], ['billingPeriod' => 'DESC']);
                $subscriptionStartedAt = $tenant->getSubscriptionStartedAt();
                if ($latestInvoice instanceof Invoice && $subscriptionStartedAt !== null && $latestInvoice->getBillingPeriod() < $subscriptionStartedAt) {
                    $latestInvoice = null;
                }
                $nextBillingDate = $latestInvoice instanceof Invoice
                    ? $latestInvoice->getBillingPeriod()->modify('+1 year')
                    : $subscriptionStartedAt?->setTime(0, 0);
                if ($nextBillingDate === null || $period < $nextBillingDate) {
                    continue;
                }
            }

            $number = sprintf('%s-%03d', $year, ++$sequence);
            $this->entityManager->persist(new Invoice($tenant, $number, $tenant->getSubscriptionPlan(), $tenant->getSubscriptionPrice(), $period, $interval));
            ++$created;
        }

        $this->entityManager->flush();

        return $created;
    }

    public function createInitialAnnualInvoice(Tenant $tenant): ?Invoice
    {
        if ($tenant->getSubscriptionInterval() !== 'annual' || $tenant->getSubscriptionPlan() === null || $tenant->getSubscriptionPrice() === null) {
            return null;
        }

        $billingDate = ($tenant->getSubscriptionStartedAt() ?? new \DateTimeImmutable())->setTime(0, 0);
        $existing = $this->entityManager->getRepository(Invoice::class)->findOneBy(['tenant' => $tenant, 'billingPeriod' => $billingDate]);
        if ($existing instanceof Invoice) {
            return $existing;
        }

        $invoice = new Invoice(
            $tenant,
            sprintf('%s-%03d', $billingDate->format('Y'), $this->currentSequence($billingDate->format('Y')) + 1),
            $tenant->getSubscriptionPlan(),
            $tenant->getSubscriptionPrice(),
            $billingDate,
            'annual',
        );
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        return $invoice;
    }

    private function currentSequence(string $year): int
    {
        $latestNumber = $this->entityManager->getRepository(Invoice::class)
            ->createQueryBuilder('invoice')
            ->select('invoice.number')
            ->where('invoice.number LIKE :prefix')
            ->setParameter('prefix', $year.'-%')
            ->orderBy('invoice.number', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return is_array($latestNumber) ? (int) substr((string) $latestNumber['number'], 5) : 0;
    }
}
