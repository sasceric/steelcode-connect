<?php

namespace App\EventListener;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Uid\Uuid;

/** Transactional change hints; the scheduler still reconciles changes made outside Doctrine. */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final class CatalogueChangeListener
{
    public function postPersist(PostPersistEventArgs $event): void
    {
        $this->changed($event);
    }

    public function postUpdate(PostUpdateEventArgs $event): void
    {
        $this->changed($event);
    }

    public function postRemove(PostRemoveEventArgs $event): void
    {
        $this->changed($event);
    }

    private function changed(PostPersistEventArgs|PostUpdateEventArgs|PostRemoveEventArgs $event): void
    {
        $entity = $event->getObject();
        $manager = $event->getObjectManager();
        $metadata = $manager->getClassMetadata($entity::class);
        $class = $metadata->getName();
        $productClasses = [
            \App\Entity\ProductTranslation::class,
            \App\Entity\CategoryProduct::class,
            \App\Entity\ProductBrand::class,
            \App\Entity\ProductPropertyAssignment::class,
            \App\Entity\ProductVariantOptionValue::class,
            \App\Entity\ProductMedia::class,
        ];
        $scope = null;
        $tenant = null;
        $id = null;
        if ($entity instanceof Product || in_array($class, $productClasses, true)) {
            $product = $entity instanceof Product ? $entity : $metadata->getFieldValue($entity, 'product');
            if (!$product instanceof Product) {
                return;
            }
            $tenant = (string) $product->getTenant()->getId();
            $id = (string) $product->getId();
            // Parent edits affect inherited variants; variant edits affect parent configurators.
            $scope = '(p.id = :local OR p.parent_id = :local OR p.id = (SELECT parent_id FROM products WHERE id = :local AND tenant_id = :tenant))';
        } else {
            $references = [
                \App\Entity\Tax::class => 'tax_id',
                \App\Entity\Manufacturer::class => 'manufacturer_id',
                \App\Entity\Unit::class => 'unit_id',
                \App\Entity\DeliveryTime::class => 'delivery_time_id',
            ];
            if (isset($references[$class])) {
                $tenant = (string) $metadata->getFieldValue($entity, 'tenant')->getId();
                $id = (string) $metadata->getFieldValue($entity, 'id');
                $scope = 'p.'.$references[$class].' = :local';
            }
        }
        if ($scope === null) {
            return;
        }
        $manager->getConnection()->executeStatement(<<<SQL
INSERT INTO integration_catalogue_dirty (tenant_id, connection_id, product_id, revision)
SELECT :tenant, c.id, p.id, :revision FROM products p
JOIN integration_connections c ON c.tenant_id = p.tenant_id
WHERE p.tenant_id = :tenant AND $scope
    AND c.connector_key IN ('shopware', 'woocommerce') AND c.enabled = TRUE AND c.status = 'active'
    AND c.configuration->'exportSettings'->>'automaticSync' = 'true'
    AND c.directions::jsonb @> '["channel"]'::jsonb
ON CONFLICT (tenant_id, connection_id, product_id) DO UPDATE SET
    revision = EXCLUDED.revision, changed_at = CURRENT_TIMESTAMP
SQL, ['tenant' => $tenant, 'local' => $id, 'revision' => (string) Uuid::v7()]);
    }
}
