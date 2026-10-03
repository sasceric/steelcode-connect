<?php

namespace App\Controller\Api;

use App\Entity\Brand;
use App\Entity\BrandTranslation;
use App\Entity\Locale;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Service\ProductBrandService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/brands')]
final class BrandController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $manager,
        ProductBrandService $brands,
    ): JsonResponse
    {
        $tenant = $this->tenant($manager);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 25)));
        $search = mb_strtolower(trim((string) $request->query->get('search', '')));
        $query = $manager->createQueryBuilder()
            ->select('brand')
            ->from(Brand::class, 'brand')
            ->where('brand.tenant = :tenant')
            ->setParameter('tenant', $tenant);
        if ($search !== '') {
            $query
                ->andWhere(
                    'EXISTS (SELECT 1 FROM '.BrandTranslation::class.' searched '
                    .'WHERE searched.brand = brand AND LOWER(searched.name) LIKE :search)',
                )
                ->setParameter('search', '%'.$search.'%');
        }
        $count = clone $query;
        $total = (int) $count->select('COUNT(brand.id)')->getQuery()->getSingleScalarResult();
        $locale = $manager->getRepository(Locale::class)->findOneBy(['code' => $tenant->getDefaultSnippetLocale()]);
        $records = $query
            ->addSelect('COALESCE(sortTranslation.name, \'\') AS HIDDEN sortName')
            ->leftJoin(
                BrandTranslation::class,
                'sortTranslation',
                'WITH',
                'sortTranslation.brand = brand AND sortTranslation.locale = :locale',
            )
            ->setParameter('locale', $locale)
            ->orderBy('sortName', 'ASC')
            ->addOrderBy('brand.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json(
            [
                'brands' => $brands->payloads($records, $manager),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'hasMore' => $page * $limit < $total,
                ],
            ],
        );
    }

    private function tenant(EntityManagerInterface $manager): Tenant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $membership = $manager->getRepository(TenantMembership::class)->forUser($user);
        if (!$membership instanceof TenantMembership) {
            throw $this->createAccessDeniedException();
        }
        return $membership->getTenant();
    }
}
