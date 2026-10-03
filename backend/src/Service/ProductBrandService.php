<?php

namespace App\Service;

use App\Entity\Brand;
use App\Entity\BrandTranslation;
use App\Entity\Product;
use App\Entity\ProductBrand;
use Doctrine\ORM\EntityManagerInterface;

final class ProductBrandService
{
    /** @return list<Brand> */
    public function brands(
        Product $product,
        EntityManagerInterface $manager,
    ): array
    {
        $brands = [];
        foreach ($this->assignments($product, $manager) as $assignment) {
            $brand = $assignment->getBrand();
            $brands[$brand->getId()->toRfc4122()] = $brand;
        }
        return array_values($brands);
    }

    /** Reconcile this connector's assignments without removing manual/other connector brands.
     * @param list<Brand> $brands
     */
    public function synchronize(
        Product $product,
        array $brands,
        string $sourceKey,
        EntityManagerInterface $manager,
    ): void
    {
        $desired = $this->validated($product, $brands);
        foreach ($this->assignments($product, $manager) as $assignment) {
            if ($assignment->getSourceKey() !== $sourceKey) {
                continue;
            }
            $id = $assignment->getBrand()->getId()->toRfc4122();
            if (!isset($desired[$id])) {
                $manager->remove($assignment);
            } else {
                unset($desired[$id]);
            }
        }
        foreach ($desired as $brand) {
            $manager->persist(new ProductBrand($product, $brand, $sourceKey));
        }
    }

    /** Explicit user edits remove deselected brands; unchanged imports retain provenance.
     * @param list<Brand> $brands
     */
    public function select(
        Product $product,
        array $brands,
        EntityManagerInterface $manager,
    ): void
    {
        $desired = $this->validated($product, $brands);
        $existing = [];
        foreach ($this->assignments($product, $manager) as $assignment) {
            $id = $assignment->getBrand()->getId()->toRfc4122();
            $existing[$id] = true;
            if (!isset($desired[$id])) {
                $manager->remove($assignment);
            }
        }
        foreach ($desired as $id => $brand) {
            if (!isset($existing[$id])) {
                $manager->persist(new ProductBrand($product, $brand));
            }
        }
    }

    /** @return list<array{id: string, name: string, labels: array<string, string>}> */
    public function payloads(
        array $brands,
        EntityManagerInterface $manager,
    ): array
    {
        if ($brands === []) {
            return [];
        }
        $translations = $manager->createQueryBuilder()
            ->select('translation', 'locale')
            ->from(BrandTranslation::class, 'translation')
            ->join('translation.locale', 'locale')
            ->where('translation.brand IN (:brands)')
            ->setParameter('brands', $brands)
            ->getQuery()
            ->getResult();
        $labels = [];
        foreach ($translations as $translation) {
            $labels[$translation->getBrand()->getId()->toRfc4122()][$translation->getLocale()->getCode()] = $translation->getName();
        }
        return array_map(
            static function (Brand $brand) use ($labels): array {
                $id = $brand->getId()->toRfc4122();
                $names = $labels[$id] ?? [];
                return [
                    'id' => $id,
                    'name' => $names[$brand->getTenant()->getDefaultSnippetLocale()]
                        ?? (reset($names) ?: $id),
                    'labels' => $names,
                ];
            },
            $brands,
        );
    }

    /** @return list<ProductBrand> */
    private function assignments(
        Product $product,
        EntityManagerInterface $manager,
    ): array
    {
        return $manager->getRepository(ProductBrand::class)->findBy(['tenant' => $product->getTenant(), 'product' => $product]);
    }

    /** @param list<Brand> $brands
     * @return array<string, Brand>
     */
    private function validated(
        Product $product,
        array $brands,
    ): array
    {
        $result = [];
        foreach ($brands as $brand) {
            if (!$brand->getTenant()->getId()->equals($product->getTenant()->getId())) {
                throw new \DomainException('Product and brand must belong to the same tenant.');
            }
            $result[$brand->getId()->toRfc4122()] = $brand;
        }
        return $result;
    }
}
