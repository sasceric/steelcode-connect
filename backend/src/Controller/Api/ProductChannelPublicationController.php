<?php

namespace App\Controller\Api;

use App\Entity\IntegrationSalesChannel;
use App\Entity\Product;
use App\Entity\ProductChannelPublication;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/products/{productId}/channel-publications')]
final class ProductChannelPublicationController extends AbstractController
{
    private const VISIBILITIES = [10, 20, 30];

    #[Route('', methods: ['GET'])]
    public function index(string $productId, EntityManagerInterface $entityManager): JsonResponse
    {
        $product = $this->product($productId, $entityManager);
        $channels = $entityManager->getRepository(IntegrationSalesChannel::class)->findBy(
            ['tenant' => $product->getTenant(), 'active' => true],
            ['name' => 'ASC'],
        );
        $publications = $entityManager->getRepository(ProductChannelPublication::class)->findBy([
            'product' => $product,
        ]);
        $publicationsByChannel = [];
        foreach ($publications as $publication) {
            $publicationsByChannel[
                $publication->getSalesChannel()->getId()->toRfc4122()
            ] = $publication;
        }

        return $this->json([
            'channels' => array_map(
                fn (IntegrationSalesChannel $channel) => $this->channelPayload(
                    $channel,
                    $publicationsByChannel[$channel->getId()->toRfc4122()] ?? null,
                ),
                $channels,
            ),
        ]);
    }

    #[Route('', methods: ['PUT'])]
    public function replace(
        string $productId,
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ): JsonResponse {
        $product = $this->product($productId, $entityManager, true);
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->json(
                ['message' => $this->message($translator, 'product.channel_visibility_invalid')],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $items = $data['publications'] ?? null;
        if (!is_array($items)) {
            return $this->json(
                ['message' => $this->message($translator, 'product.channel_visibility_invalid')],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $submittedIds = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                return $this->invalid($translator);
            }

            $salesChannelId = $item['salesChannelId'] ?? null;
            $visibility = $item['visibility'] ?? null;
            if (
                !is_string($salesChannelId)
                || !Uuid::isValid($salesChannelId)
                || !is_int($visibility)
                || !in_array($visibility, [0, ...self::VISIBILITIES], true)
                || isset($submittedIds[$salesChannelId])
            ) {
                return $this->invalid($translator);
            }

            $submittedIds[$salesChannelId] = true;
        }

        $existing = [];
        foreach ($entityManager->getRepository(ProductChannelPublication::class)->findBy([
            'product' => $product,
        ]) as $publication) {
            $existing[$publication->getSalesChannel()->getId()->toRfc4122()] = $publication;
        }

        foreach ($items as $item) {
            $salesChannelId = $item['salesChannelId'];
            $visibility = $item['visibility'];
            $channel = $entityManager->getRepository(IntegrationSalesChannel::class)->findOneBy([
                'id' => Uuid::fromString($salesChannelId),
                'tenant' => $product->getTenant(),
                'active' => true,
            ]);
            if (!$channel instanceof IntegrationSalesChannel) {
                return $this->invalid($translator);
            }

            $publication = $existing[$salesChannelId] ?? null;
            if ($visibility === 0) {
                if ($publication instanceof ProductChannelPublication) {
                    $entityManager->remove($publication);
                }

                continue;
            }

            if ($publication instanceof ProductChannelPublication) {
                $publication->update($visibility);

                continue;
            }

            $entityManager->persist(new ProductChannelPublication(
                $product->getTenant(),
                $product,
                $channel,
                $visibility,
            ));
        }

        $entityManager->flush();

        return $this->index($productId, $entityManager);
    }

    private function invalid(TranslatorInterface $translator): JsonResponse
    {
        return $this->json(
            ['message' => $this->message($translator, 'product.channel_visibility_invalid')],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    private function product(
        string $id,
        EntityManagerInterface $entityManager,
        bool $ownerRequired = false,
    ): Product {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $product = $entityManager->getRepository(Product::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $this->tenant($entityManager, $ownerRequired),
        ]);
        if (!$product instanceof Product) {
            throw $this->createNotFoundException();
        }

        return $product;
    }

    private function tenant(
        EntityManagerInterface $entityManager,
        bool $ownerRequired = false,
    ): Tenant {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $membership = $entityManager->getRepository(TenantMembership::class)->findOneBy([
            'user' => $user,
        ]);
        if (
            !$membership instanceof TenantMembership
            || ($ownerRequired && $membership->getRole() !== 'owner')
        ) {
            throw $this->createAccessDeniedException();
        }

        return $membership->getTenant();
    }

    private function message(TranslatorInterface $translator, string $key): string
    {
        $user = $this->getUser();

        return $translator->trans(
            $key,
            locale: $user instanceof User ? $user->getLocale() : 'bs',
        );
    }

    /** @return array<string, mixed> */
    private function channelPayload(
        IntegrationSalesChannel $channel,
        ?ProductChannelPublication $publication,
    ): array {
        return [
            'id' => $channel->getId()->toRfc4122(),
            'name' => $channel->getName(),
            'connectionName' => $channel->getConnection()->getName(),
            'connectorKey' => $channel->getConnection()->getConnectorKey(),
            'visibility' => $publication?->getVisibility() ?? 0,
        ];
    }
}
