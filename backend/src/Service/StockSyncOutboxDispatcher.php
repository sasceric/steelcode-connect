<?php

namespace App\Service;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationSecret;
use App\Entity\InventoryLevel;
use App\Entity\InventorySyncOutbox;
use App\Entity\IntegrationSalesSyncCursor;
use App\Entity\ProductChannelPublication;
use App\Entity\Product;
use App\Integration\SecretCipher;
use App\Integration\ShopwareClient;
use Doctrine\ORM\EntityManagerInterface;

final class StockSyncOutboxDispatcher
{
    public function __construct(
        private readonly SecretCipher $cipher,
        private readonly ShopwareClient $shopware,
    ) {
    }

    public function dispatch(InventorySyncOutbox $event, EntityManagerInterface $entityManager): void
    {
        if ($entityManager->getRepository(Product::class)->findOneBy(['parent' => $event->getProduct()]) instanceof Product) {
            $event->markDispatched();

            return;
        }

        $availableQuantity = $this->availableQuantity($event, $entityManager);
        $publications = $entityManager->getRepository(ProductChannelPublication::class)->findBy([
            'tenant' => $event->getTenant(),
            'product' => $event->getProduct(),
        ]);

        foreach ($publications as $publication) {
            if ($publication->getVisibility() === 0) {
                continue;
            }

            $channel = $publication->getSalesChannel();
            $connection = $channel->getConnection();
            if (!$channel->isActive() || !$connection->isEnabled() || $connection->getStatus() !== 'active') {
                continue;
            }
            $settings = $connection->getConfiguration()['importSettings'] ?? [];
            if (!is_array($settings) || ($settings['stockAuthority'] ?? null) !== 'connect') {
                continue;
            }
            if (($settings['salesContinuousSync'] ?? false) !== true) {
                throw new StockSyncNotReadyException('Shopware Sales sync is disabled for a stock-managed connection.');
            }
            $startedAt = $settings['salesContinuousStartedAt'] ?? null;
            $cursor = $entityManager->getRepository(IntegrationSalesSyncCursor::class)->findOneBy([
                'connection' => $connection,
            ]);
            if (
                !is_string($startedAt)
                || $startedAt === ''
                || !$cursor instanceof IntegrationSalesSyncCursor
                || $cursor->getStartedAt() != new \DateTimeImmutable($startedAt)
                || $cursor->getLastSyncedAt() <= $cursor->getStartedAt()
                || $cursor->getLastSyncedAt() <= $event->getUpdatedAt()
            ) {
                throw new StockSyncNotReadyException('Shopware Sales must be synchronized before publishing stock.');
            }
            if ($connection->getConnectorKey() !== 'shopware') {
                throw new \RuntimeException(sprintf(
                    'Stock publication for connector "%s" is not implemented.',
                    $connection->getConnectorKey(),
                ));
            }
            if ($publication->getExternalProductId() === null) {
                throw new \RuntimeException('The published Shopware product has no external ID.');
            }

            $this->shopware->updateProductStock(
                (string) ($connection->getConfiguration()['baseUrl'] ?? ''),
                $this->secrets($connection, $entityManager),
                $publication->getExternalProductId(),
                $availableQuantity,
            );
        }

        $event->markDispatched();
    }

    private function availableQuantity(
        InventorySyncOutbox $event,
        EntityManagerInterface $entityManager,
    ): string {
        $available = 0.0;
        $levels = $entityManager->getRepository(InventoryLevel::class)->findBy([
            'tenant' => $event->getTenant(),
            'product' => $event->getProduct(),
        ]);
        foreach ($levels as $level) {
            if ($level->getWarehouse()->isActive() && $level->getWarehouse()->isFulfillmentEnabled()) {
                $available += (float) $level->getAvailableQuantity();
            }
        }

        return number_format(max(0, $available), 4, '.', '');
    }

    /** @return array<string, string> */
    private function secrets(
        IntegrationConnection $connection,
        EntityManagerInterface $entityManager,
    ): array
    {
        $values = [];
        foreach ($entityManager->getRepository(IntegrationSecret::class)->findBy(['connection' => $connection]) as $secret) {
            $values[$secret->getSecretKey()] = $this->cipher->decrypt(
                $secret->getCiphertext(),
                $secret->getNonce(),
            );
        }

        return $values;
    }
}
