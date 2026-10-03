<?php

namespace App\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\Product;
use App\Entity\Customer;
use App\Entity\SalesOrder;
use App\Service\InventorySyncOutboxService;
use App\Service\SalesOrderIngestionService;
use Doctrine\ORM\EntityManagerInterface;

final class WooCommerceSalesRecordIngestor
{
    public function __construct(
        private readonly WooCommerceSalesMapper $mapper,
        private readonly WooCommerceCatalogueImporter $catalogue,
        private readonly SalesOrderIngestionService $ingestion,
        private readonly InventorySyncOutboxService $outbox,
        private readonly WooCommerceCustomFieldImporter $customFields,
    )
    {
    }

    public function customer(
        array $source,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
    ): void
    {
        $customer = $this->mapper->customer($source);
        $existing = $manager->getRepository(Customer::class)->findOneBy([
            'tenant' => $connection->getTenant(),
            'connection' => $connection,
            'externalId' => (string) $source['id'],
        ]);
        $customer['customFields'] = $this->customFields->values(
            $connection,
            'customer',
            $source['meta_data'] ?? [],
            $manager,
            $this->preserveNonSourceFields($existing?->getSourcePayload() ?? []),
        );
        $this->ingestion->ingestCustomer(
            $connection,
            $customer,
            $this->mapper->address($source['billing'] ?? [], 'billing'),
            $this->mapper->address($source['shipping'] ?? [], 'shipping'),
            $manager,
            [],
            true,
        );
    }

    public function order(
        array $source,
        IntegrationConnection $connection,
        EntityManagerInterface $manager,
        bool $historical,
    ): SalesOrder
    {
        $products = [];
        foreach ($source['line_items'] ?? [] as $index => $line) {
            $externalId = (string) ($line['variation_id'] ?? 0 ?: $line['product_id'] ?? 0);
            $product = $this->catalogue->mapped(
                $connection,
                'product',
                $externalId,
                Product::class,
                $manager,
            );
            // Never bind an unmapped source item to an unrelated tenant SKU.
            $source['line_items'][$index]['resolvedSku'] = $product instanceof Product ? $product->getSku() : '';
            if ($product instanceof Product) {
                $products[(string) $product->getId()] = $product;
            }
        }
        $event = $this->mapper->order($source);
        $existing = $manager->getRepository(SalesOrder::class)->findOneBy(
            [
                'tenant' => $connection->getTenant(),
                'connection' => $connection,
                'externalId' => $event['externalId'],
            ],
        );
        $event['sourcePayload']['customFields'] = $this->customFields->values(
            $connection,
            'order',
            $source['meta_data'] ?? [],
            $manager,
            $this->preserveNonSourceFields($existing?->getSourcePayload() ?? []),
        );
        $sourceChanged = !$existing instanceof SalesOrder
            || ($existing->getSourcePayload()['sourceFields'] ?? []) !== $event['sourcePayload']['sourceFields'];
        $database = $manager->getConnection();
        $database->beginTransaction();
        try {
            $order = $this->ingestion->ingest(
                $connection,
                $event,
                $manager,
                !$historical,
            );
            if (!$historical && $order->getStatus() !== 'historical') {
                if (in_array($source['status'] ?? null, ['cancelled', 'failed', 'refunded'], true)) {
                    // A financial refund is NOT a physical restock. Cancellation only
                    // releases unshipped reservations; fulfilled stock stays deducted.
                    $event['type'] = 'cancelled';
                    $this->ingestion->ingest($connection, $event, $manager);
                    $order->updateSourcePayload($event['sourcePayload']);
                } else {
                    $this->ingestion->reconcileDeliveryFulfillment($order, $manager);
                }
                if ($sourceChanged && ($connection->getConfiguration()['importSettings']['stockAuthority'] ?? null) === 'connect') {
                    // Woo can reduce/restore native stock on payment/state changes.
                    // Republish absolute available stock after consuming that change,
                    // even when the Connect reservation itself did not change.
                    foreach ($products as $product) {
                        $this->outbox->queue($connection->getTenant(), $product, $manager);
                    }
                }
            }
            $manager->flush();
            $database->commit();
            return $order;
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }
    }

    private function preserveNonSourceFields(array $payload): array
    {
        $fields = $payload['customFields'] ?? [];
        // The previous read-only Sales importer stored raw Woo keys directly.
        // Remove only values demonstrably identical to its saved source data.
        foreach (SourcePayloadSanitizer::sanitize($payload['sourceFields']['meta_data'] ?? []) as $item) {
            $key = $item['key'] ?? null;
            if (is_string($key) && array_key_exists($key, $fields) && $fields[$key] === ($item['value'] ?? null)) {
                unset($fields[$key]);
            }
        }

        return $fields;
    }
}
