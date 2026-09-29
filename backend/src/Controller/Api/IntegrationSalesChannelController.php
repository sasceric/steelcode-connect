<?php

namespace App\Controller\Api;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationSalesChannel;
use App\Entity\IntegrationSecret;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Integration\SecretCipher;
use App\Integration\ShopwareClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/integrations')]
final class IntegrationSalesChannelController extends AbstractController
{
    #[Route('/{id}/sales-channels', methods: ['GET'])]
    public function index(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $connection = $this->connection($id, $entityManager);
        $channels = $entityManager->getRepository(IntegrationSalesChannel::class)->findBy(
            ['connection' => $connection],
            ['name' => 'ASC'],
        );

        return $this->json([
            'salesChannels' => array_map($this->payload(...), $channels),
        ]);
    }

    #[Route('/{id}/sales-channels/sync', methods: ['POST'])]
    public function sync(
        string $id,
        EntityManagerInterface $entityManager,
        SecretCipher $cipher,
        ShopwareClient $shopwareClient,
        TranslatorInterface $translator,
    ): JsonResponse {
        $connection = $this->connection($id, $entityManager, true);
        if ($connection->getConnectorKey() !== 'shopware') {
            return $this->json(
                ['message' => $this->message($translator, 'integration.sales_channels_unavailable')],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $secrets = $this->secrets($connection, $entityManager, $cipher);
        $baseUrl = (string) ($connection->getConfiguration()['baseUrl'] ?? '');
        try {
            $channels = $shopwareClient->salesChannels($baseUrl, $secrets);
        } catch (\Throwable) {
            return $this->json(
                ['message' => $this->message($translator, 'integration.shopware_connection_failed')],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        foreach ($channels as $channel) {
            $salesChannel = $entityManager
                ->getRepository(IntegrationSalesChannel::class)
                ->findOneBy([
                    'connection' => $connection,
                    'externalId' => $channel['id'],
                ]);

            if ($salesChannel instanceof IntegrationSalesChannel) {
                $salesChannel->update(
                    $channel['name'],
                    $channel['type'],
                    $channel['active'],
                    $channel['sourceData'],
                );

                continue;
            }

            $entityManager->persist(new IntegrationSalesChannel(
                $connection->getTenant(),
                $connection,
                $channel['id'],
                $channel['name'],
                $channel['type'],
                $channel['active'],
                $channel['sourceData'],
            ));
        }

        $entityManager->flush();

        return $this->json([
            'message' => $this->message($translator, 'integration.sales_channels_synced'),
            'salesChannels' => array_map(
                $this->payload(...),
                $entityManager->getRepository(IntegrationSalesChannel::class)->findBy(
                    ['connection' => $connection],
                    ['name' => 'ASC'],
                ),
            ),
        ]);
    }

    private function connection(
        string $id,
        EntityManagerInterface $entityManager,
        bool $ownerRequired = false,
    ): IntegrationConnection {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $connection = $entityManager->getRepository(IntegrationConnection::class)->findOneBy([
            'id' => Uuid::fromString($id),
            'tenant' => $this->tenant($entityManager, $ownerRequired),
        ]);
        if (!$connection instanceof IntegrationConnection) {
            throw $this->createNotFoundException();
        }

        return $connection;
    }

    /** @return array<string, string> */
    private function secrets(
        IntegrationConnection $connection,
        EntityManagerInterface $entityManager,
        SecretCipher $cipher,
    ): array {
        $values = [];
        foreach ($entityManager->getRepository(IntegrationSecret::class)->findBy([
            'connection' => $connection,
        ]) as $secret) {
            $values[$secret->getSecretKey()] = $cipher->decrypt(
                $secret->getCiphertext(),
                $secret->getNonce(),
            );
        }

        return $values;
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
    private function payload(IntegrationSalesChannel $channel): array
    {
        return [
            'id' => $channel->getId()->toRfc4122(),
            'connectionId' => $channel->getConnection()->getId()->toRfc4122(),
            'connectionName' => $channel->getConnection()->getName(),
            'connectorKey' => $channel->getConnection()->getConnectorKey(),
            'externalId' => $channel->getExternalId(),
            'name' => $channel->getName(),
            'type' => $channel->getType(),
            'active' => $channel->isActive(),
        ];
    }
}
