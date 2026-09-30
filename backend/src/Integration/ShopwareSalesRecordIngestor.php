<?php

namespace App\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\SalesOrder;
use App\Service\SalesOrderIngestionService;
use Doctrine\ORM\EntityManagerInterface;

final class ShopwareSalesRecordIngestor
{
    public function __construct(
        private readonly ShopwareSalesMapper $mapper,
        private readonly SalesOrderIngestionService $ingestion,
    ) {
    }

    /**
     * @param array<string, mixed> $source
     * @param list<array<string, mixed>> $included
     */
    public function customer(
        array $source,
        array $included,
        IntegrationConnection $connection,
        EntityManagerInterface $entityManager,
    ): void {
        $source = $this->mapper->withIncluded($source, $included);
        $profile = $this->mapper->customer($source);
        $attributes = is_array($source['attributes'] ?? null) ? $source['attributes'] : $source;
        $addressBookComplete = is_array($attributes['addresses'] ?? null)
            || is_array($source['relationships']['addresses']['data'] ?? null);

        $this->ingestion->ingestCustomer(
            $connection,
            $profile,
            $this->mapper->address($attributes['defaultBillingAddress'] ?? []),
            $this->mapper->address($attributes['defaultShippingAddress'] ?? []),
            $entityManager,
            $this->mapper->customerAddresses($source),
            $addressBookComplete,
        );
    }

    /**
     * @param array<string, mixed> $source
     * @param list<array<string, mixed>> $included
     */
    public function order(
        array $source,
        array $included,
        IntegrationConnection $connection,
        EntityManagerInterface $entityManager,
        bool $historical,
    ): SalesOrder {
        $event = $this->mapper->order($this->mapper->withIncluded($source, $included));
        $database = $entityManager->getConnection();
        $database->beginTransaction();
        try {
            $order = $this->ingestion->ingest($connection, $event, $entityManager, !$historical);
            if (!$historical) {
                if (($event['sourcePayload']['state'] ?? null) === 'cancelled') {
                    $event['type'] = 'cancelled';
                    $this->ingestion->ingest($connection, $event, $entityManager);
                } else {
                    $this->ingestion->reconcileDeliveryFulfillment($order, $entityManager);
                }
            }
            $database->commit();

            return $order;
        } catch (\Throwable $exception) {
            $database->rollBack();

            throw $exception;
        }
    }
}
