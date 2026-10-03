<?php

namespace App\Service;

use App\Entity\Property;
use App\Entity\PropertyTranslation;
use Doctrine\ORM\EntityManagerInterface;

final class PropertyValueReader
{
    /** @param list<Property> $properties */
    public function payloads(array $properties, EntityManagerInterface $manager): array
    {
        if ($properties === []) {
            return [];
        }
        $translations = $manager->createQueryBuilder()
            ->select('IDENTITY(translation.property) AS propertyId, locale.code AS localeCode, translation.name AS name')
            ->from(PropertyTranslation::class, 'translation')
            ->join('translation.locale', 'locale')
            ->join('translation.property', 'property')
            ->where('translation.property IN (:properties)')
            ->andWhere('property.tenant = :tenant')
            ->setParameter('properties', $properties)
            ->setParameter('tenant', $properties[0]->getPropertyGroup()->getTenant())
            ->getQuery()
            ->getArrayResult();
        $labels = [];
        foreach ($translations as $translation) {
            $labels[$translation['propertyId']][$translation['localeCode']] = $translation['name'];
        }

        return array_map(static function (Property $property) use ($labels): array {
            $id = $property->getId()->toRfc4122();

            return [
                'id' => $id,
                'propertyGroupId' => $property->getPropertyGroup()->getId()->toRfc4122(),
                'name' => $property->getName(),
                'labels' => $labels[$id] ?? [],
                'code' => $property->getCode(),
                'colorHex' => $property->getColorHex(),
                'position' => $property->getPosition(),
            ];
        }, $properties);
    }
}
