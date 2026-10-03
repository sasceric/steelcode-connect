<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductVariantOptionGroupProperty;
use App\Entity\ProductVariantOptionValue;
use App\Entity\Property;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class ProductVariantConfigurationReader
{
    public function __construct(private PropertyValueReader $values)
    {
    }

    public function read(Product $parent, EntityManagerInterface $manager): array
    {
        $variants = $manager->createQueryBuilder()
            ->select('variant.id, variant.sku, variant.optionValues')
            ->from(Product::class, 'variant')
            ->where('variant.parent = :parent')
            ->andWhere('variant.tenant = :tenant')
            ->setParameter('parent', $parent)
            ->setParameter('tenant', $parent->getTenant())
            ->orderBy('variant.createdAt', 'ASC')
            ->addOrderBy('variant.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
        $assigned = $manager->createQueryBuilder()
            ->select('IDENTITY(value.product) AS productId, property.id AS propertyId')
            ->from(ProductVariantOptionValue::class, 'value')
            ->join('value.product', 'variant')
            ->join('value.property', 'property')
            ->where('variant.parent = :parent')
            ->andWhere('variant.tenant = :tenant')
            ->andWhere('value.tenant = :tenant')
            ->andWhere('property.tenant = :tenant')
            ->setParameter('parent', $parent)
            ->setParameter('tenant', $parent->getTenant())
            ->getQuery()
            ->getArrayResult();
        $configured = $manager->createQueryBuilder()
            ->select('property.id')
            ->from(ProductVariantOptionGroupProperty::class, 'value')
            ->join('value.optionGroup', 'optionGroup')
            ->join('value.property', 'property')
            ->join('property.propertyGroup', 'propertyGroup')
            ->where('optionGroup.product = :parent')
            ->andWhere('optionGroup.tenant = :tenant')
            ->andWhere('value.tenant = :tenant')
            ->andWhere('property.tenant = :tenant')
            ->andWhere('propertyGroup.tenant = :tenant')
            ->setParameter('parent', $parent)
            ->setParameter('tenant', $parent->getTenant())
            ->getQuery()
            ->getArrayResult();
        $ids = [];
        foreach ($parent->getAttributeConfiguration() as $attributes) {
            foreach ($attributes as $attribute) {
                if (empty($attribute['variation'])) {
                    continue;
                }
                foreach ($attribute['propertyIds'] ?? [] as $id) {
                    if (is_string($id) && Uuid::isValid($id)) {
                        $ids[$id] = Uuid::fromString($id);
                    }
                }
            }
        }
        $assignedByVariant = [];
        foreach ($assigned as $row) {
            $id = (string) $row['propertyId'];
            $ids[$id] = Uuid::fromString($id);
            $assignedByVariant[(string) $row['productId']][] = $id;
        }
        foreach ($configured as $row) {
            $id = (string) $row['id'];
            $ids[$id] = Uuid::fromString($id);
        }
        $legacyNames = [];
        $legacyGroups = [];
        foreach ($variants as $variant) {
            foreach ($variant['optionValues'] as $groupName => $value) {
                if (is_string($value) && Uuid::isValid($value)) {
                    $ids[$value] = Uuid::fromString($value);
                } elseif (is_string($value) && $value !== '' && $value !== '*') {
                    $legacyNames[$value] = $value;
                    $legacyGroups[$groupName] = $groupName;
                }
            }
        }
        $properties = $ids === [] ? [] : $manager->createQueryBuilder()
            ->select('property', 'propertyGroup')
            ->from(Property::class, 'property')
            ->join('property.propertyGroup', 'propertyGroup')
            ->where('property.id IN (:ids)')
            ->andWhere('property.tenant = :tenant')
            ->andWhere('propertyGroup.tenant = :tenant')
            ->setParameter('ids', array_values($ids))
            ->setParameter('tenant', $parent->getTenant())
            ->getQuery()
            ->getResult();
        $propertiesById = [];
        foreach ($properties as $property) {
            $propertiesById[(string) $property->getId()] = $property;
        }
        $legacyByLabel = [];
        if ($legacyNames !== []) {
            $legacyProperties = $manager->createQueryBuilder()
                ->select('property', 'propertyGroup')
                ->from(Property::class, 'property')
                ->join('property.propertyGroup', 'propertyGroup')
                ->where('property.tenant = :tenant')
                ->andWhere('propertyGroup.tenant = :tenant')
                ->andWhere('property.name IN (:names)')
                ->andWhere('propertyGroup.name IN (:groups)')
                ->setParameter('tenant', $parent->getTenant())
                ->setParameter('names', array_values($legacyNames))
                ->setParameter('groups', array_values($legacyGroups))
                ->getQuery()
                ->getResult();
            foreach ($legacyProperties as $property) {
                $key = json_encode([$property->getPropertyGroup()->getName(), $property->getName()]);
                // A duplicated group/value label cannot safely identify an existing combination.
                $legacyByLabel[$key] = array_key_exists($key, $legacyByLabel) ? null : $property;
            }
        }
        $groups = [];
        $existing = [];
        foreach ($variants as $variant) {
            $variantIds = $assignedByVariant[(string) $variant['id']] ?? [];
            foreach ($variant['optionValues'] as $groupName => $value) {
                if (is_string($value) && isset($propertiesById[$value])) {
                    $variantIds[] = $value;
                } else {
                    $property = $legacyByLabel[json_encode([$groupName, $value])] ?? null;
                    if ($property instanceof Property) {
                        $propertyId = (string) $property->getId();
                        $propertiesById[$propertyId] = $property;
                        $variantIds[] = $propertyId;
                    }
                }
            }
            $variantIds = array_values(array_unique($variantIds));
            sort($variantIds);
            $existing[] = [
                'id' => (string) $variant['id'],
                'sku' => $variant['sku'],
                'propertyIds' => $variantIds,
                'optionValues' => $variant['optionValues'],
                'wildcardGroupIds' => array_keys(array_filter(
                    $variant['optionValues'],
                    static fn(mixed $value): bool => $value === '*',
                )),
            ];
        }
        $properties = array_values($propertiesById);
        foreach ($properties as $property) {
            $groupId = (string) $property->getPropertyGroup()->getId();
            $groups[$groupId][] = (string) $property->getId();
        }

        return [
            'optionGroups' => array_map(
                static fn (string $id, array $propertyIds): array => [
                    'propertyGroupId' => $id,
                    'propertyIds' => $propertyIds,
                ],
                array_keys($groups),
                array_values($groups),
            ),
            'properties' => $this->values->payloads($properties, $manager),
            'existingCombinations' => $existing,
            'attributeConfiguration' => $parent->getAttributeConfiguration(),
        ];
    }
}
