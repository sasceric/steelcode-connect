<?php

namespace App\Controller\Api;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\IntegrationSalesSyncCursor;
use App\Entity\IntegrationSecret;
use App\Entity\SalesOrder;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use App\Integration\AnanasConnectionTester;
use App\Integration\ConnectorCatalog;
use App\Integration\SecretCipher;
use App\Integration\ShopwareConnectionTester;
use App\Integration\WooCommerceStockPublisher;
use App\Service\InventorySyncOutboxService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/api/v1/integrations')]
final class IntegrationController extends AbstractController
{
    public function __construct(
        private readonly \App\Integration\WooCommerceClient $wooCommerceClient,
    )
    {
    }

    #[Route('', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $connections = $entityManager->getRepository(IntegrationConnection::class)->findBy(['tenant' => $tenant], ['createdAt' => 'DESC']);

        return $this->json([
            'definitions' => ConnectorCatalog::all(),
            'connections' => array_map($this->payload(...), $connections),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, SecretCipher $cipher, ShopwareConnectionTester $shopwareTester, AnanasConnectionTester $ananasTester, TranslatorInterface $translator): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['message' => $this->message($translator, 'integration.json_required')], Response::HTTP_BAD_REQUEST);
        }

        $definition = ConnectorCatalog::find((string) ($data['connectorKey'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($definition === null || $name === '') {
            return $this->json(['message' => $this->message($translator, 'integration.configuration_invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $configuration = is_array($data['configuration'] ?? null) ? $data['configuration'] : [];
        $secrets = is_array($data['secrets'] ?? null) ? $data['secrets'] : [];
        $missingConfiguration = array_filter($definition['configuration'], fn (string $key): bool => empty($configuration[$key]));
        $missingSecrets = array_filter($definition['credentials'], fn (string $key): bool => empty($secrets[$key]));
        if ($missingConfiguration !== [] || $missingSecrets !== []) {
            return $this->json(['message' => $this->message($translator, 'integration.missing_requirements')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $testMessage = $this->message($translator, 'integration.connection_successful');
        try {
            $this->testConnector($definition['key'], $configuration, $secrets, $shopwareTester, $ananasTester);
        } catch (\Throwable) {
            return $this->json(['message' => $this->message($translator, $this->connectionFailedKey($definition['key']))], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $connection = new IntegrationConnection($tenant, $definition['key'], $name, $definition['directions'], $configuration);
        $connection->activate($testMessage);
        $entityManager->persist($connection);
        foreach ($secrets as $key => $value) {
            if (!is_string($key) || !in_array($key, $definition['credentials'], true) || !is_string($value) || $value === '') {
                continue;
            }
            $encrypted = $cipher->encrypt($value);
            $entityManager->persist(new IntegrationSecret($connection, $key, $encrypted['ciphertext'], $encrypted['nonce']));
        }
        $entityManager->flush();

        return $this->json(['connection' => $this->payload($connection)], Response::HTTP_CREATED);
    }

    #[Route('/test', methods: ['POST'])]
    public function testDraft(Request $request, EntityManagerInterface $entityManager, ShopwareConnectionTester $shopwareTester, AnanasConnectionTester $ananasTester, TranslatorInterface $translator): JsonResponse
    {
        $this->tenant($entityManager, true);
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['message' => $this->message($translator, 'integration.json_required')], Response::HTTP_BAD_REQUEST);
        }
        $definition = ConnectorCatalog::find((string) ($data['connectorKey'] ?? ''));
        $configuration = is_array($data['configuration'] ?? null) ? $data['configuration'] : [];
        $secrets = is_array($data['secrets'] ?? null) ? $data['secrets'] : [];
        if ($definition === null || array_filter($definition['configuration'], fn (string $key): bool => empty($configuration[$key])) !== [] || array_filter($definition['credentials'], fn (string $key): bool => empty($secrets[$key])) !== []) {
            return $this->json(['message' => $this->message($translator, 'integration.missing_requirements')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $this->testConnector($definition['key'], $configuration, $secrets, $shopwareTester, $ananasTester);
        } catch (\Throwable) {
            return $this->json(['message' => $this->message($translator, $this->connectionFailedKey($definition['key']))], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (in_array($definition['key'], ['shopware', 'ananas', 'woocommerce'], true)) {
            return $this->json(['message' => $this->message($translator, 'integration.connection_successful')]);
        }

        return $this->json(['message' => $this->message($translator, 'integration.configuration_complete')]);
    }

    #[Route('/{id}/test', methods: ['POST'])]
    public function test(string $id, EntityManagerInterface $entityManager, SecretCipher $cipher, ShopwareConnectionTester $shopwareTester, AnanasConnectionTester $ananasTester, TranslatorInterface $translator): JsonResponse
    {
        $tenant = $this->tenant($entityManager, true);
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $connection = $entityManager->getRepository(IntegrationConnection::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant]);
        if (!$connection instanceof IntegrationConnection) {
            throw $this->createNotFoundException();
        }
        $definition = ConnectorCatalog::find($connection->getConnectorKey());
        if ($definition === null) {
            throw $this->createNotFoundException();
        }

        $missingConfiguration = array_filter($definition['configuration'], fn (string $key): bool => empty($connection->getConfiguration()[$key]));
        $secrets = $entityManager->getRepository(IntegrationSecret::class)->findBy(['connection' => $connection]);
        $storedKeys = array_map(static fn (IntegrationSecret $secret): string => $secret->getSecretKey(), $secrets);
        $missingSecrets = array_diff($definition['credentials'], $storedKeys);

        if ($missingConfiguration !== [] || $missingSecrets !== []) {
            $message = $this->message($translator, 'integration.missing_requirements');
            $connection->setTestResult('failed', $message);
            $entityManager->flush();

            return $this->json(['message' => $message, 'connection' => $this->payload($connection)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $secretValues = [];
        foreach ($secrets as $secret) {
            $secretValues[$secret->getSecretKey()] = $cipher->decrypt($secret->getCiphertext(), $secret->getNonce());
        }
        if (in_array($connection->getConnectorKey(), ['shopware', 'ananas', 'woocommerce'], true)) {
            try {
                $this->testConnector($connection->getConnectorKey(), $connection->getConfiguration(), $secretValues, $shopwareTester, $ananasTester);
                $connection->activate($this->message($translator, 'integration.connection_successful'));
            } catch (\Throwable) {
                $message = $this->message($translator, $this->connectionFailedKey($connection->getConnectorKey()));
                $connection->setTestResult('failed', $message);
                $entityManager->flush();

                return $this->json(['message' => $message, 'connection' => $this->payload($connection)], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        } else {
            $connection->setTestResult('configured', $this->message($translator, 'integration.configuration_complete'));
        }
        $entityManager->flush();

        return $this->json(['message' => $connection->getStatus() === 'active' ? $this->message($translator, 'integration.connection_successful') : $this->message($translator, 'integration.configuration_complete'), 'connection' => $this->payload($connection)]);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $request, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $connection = $this->connection($id, $entityManager);
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->json(['message' => $this->message($translator, 'integration.json_required')], Response::HTTP_BAD_REQUEST);
        }
        if (!is_bool($data['enabled'] ?? null)) {
            return $this->json(['message' => $this->message($translator, 'integration.configuration_invalid')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $enabled = $data['enabled'];
        $connection->setEnabled($enabled, $this->message($translator, $enabled ? 'integration.activated' : 'integration.deactivated'));
        $entityManager->flush();

        return $this->json(['message' => $this->message($translator, $enabled ? 'integration.activated' : 'integration.deactivated'), 'connection' => $this->payload($connection)]);
    }

    #[Route('/{id}/configuration', methods: ['GET'])]
    public function configuration(
        string $id,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $connection = $this->connection($id, $entityManager);

        return $this->json([
            'connection' => $this->payload($connection),
            'importSettings' => $this->importSettings($connection),
        ]);
    }

    #[Route('/{id}/sales-sync', methods: ['GET'])]
    public function salesSyncStatus(string $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $connection = $this->connection($id, $entityManager);
        $cursor = $entityManager->getRepository(IntegrationSalesSyncCursor::class)->findOneBy([
            'connection' => $connection,
        ]);
        $settings = $this->importSettings($connection);

        return $this->json([
            'enabled' => $settings['salesContinuousSync'],
            'startedAt' => $settings['salesContinuousStartedAt'],
            'lastSyncedAt' => $cursor instanceof IntegrationSalesSyncCursor
                && $cursor->getLastSyncedAt() > $cursor->getStartedAt()
                    ? $cursor->getLastSyncedAt()->format(\DateTimeInterface::ATOM)
                    : null,
            'lastError' => $cursor?->getLastError(),
            'lastErrorAt' => $cursor?->getLastErrorAt()?->format(\DateTimeInterface::ATOM),
            'pendingOrders' => $entityManager->getRepository(SalesOrder::class)->count([
                'connection' => $connection,
                'status' => 'new',
            ]),
        ]);
    }

    #[Route('/{id}/configuration', methods: ['PATCH'])]
    public function updateConfiguration(
        string $id,
        Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
        InventorySyncOutboxService $stockOutbox,
        WooCommerceStockPublisher $wooStock,
    ): JsonResponse {
        $connection = $this->connection($id, $entityManager, true);
        try {
            $data = $request->toArray();
        } catch (\JsonException) {
            return $this->json([
                'message' => $this->message($translator, 'integration.json_required'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $settings = $data['importSettings'] ?? null;
        if (!is_array($settings)) {
            return $this->json([
                'message' => $this->message($translator, 'integration.configuration_invalid'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $configuration = $connection->getConfiguration();
        $previousSettings = $this->importSettings($connection);
        $normalizedSettings = $this->normalizeImportSettings($settings);
        if (
            !in_array($connection->getConnectorKey(), ['shopware', 'woocommerce'], true)
            && ($normalizedSettings['salesContinuousSync'] || $normalizedSettings['stockAuthority'] === 'connect')
        ) {
            return $this->json([
                'message' => $this->message($translator, 'integration.configuration_invalid'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (
            $normalizedSettings['salesContinuousSync']
            && (
                !$normalizedSettings['areas']['salesOrders']
                || !in_array('channel', $connection->getDirections(), true)
            )
        ) {
            return $this->json([
                'message' => $this->message($translator, 'integration.configuration_invalid'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($normalizedSettings['stockAuthority'] === 'connect' && !$normalizedSettings['salesContinuousSync']) {
            return $this->json([
                'message' => $this->message($translator, 'integration.configuration_invalid'),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $activateStockSync = $normalizedSettings['stockAuthority'] === 'connect'
            && $previousSettings['stockAuthority'] !== 'connect';
        if ($activateStockSync) {
            if ($connection->getConnectorKey() === 'woocommerce' && $wooStock->hasParentStockPools($connection, $entityManager)) {
                return $this->json([
                    'message' => $this->message($translator, 'integration.woo_parent_stock_unsupported'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $cursor = $entityManager->getRepository(IntegrationSalesSyncCursor::class)->findOneBy([
                'connection' => $connection,
            ]);
            if (
                !$previousSettings['salesContinuousSync']
                || $previousSettings['salesContinuousStartedAt'] === null
                || !$cursor instanceof IntegrationSalesSyncCursor
                || $cursor->getStartedAt() != new \DateTimeImmutable($previousSettings['salesContinuousStartedAt'])
                || $cursor->getLastSyncedAt() <= $cursor->getStartedAt()
                || $cursor->getLastSyncedAt() < new \DateTimeImmutable('-5 minutes')
                || $cursor->getLastError() !== null
            ) {
                return $this->json([
                    'message' => $this->message($translator, 'integration.stock_sync_not_ready'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }
        if ($normalizedSettings['salesContinuousSync'] && !$previousSettings['salesContinuousSync']) {
            $activeRun = $entityManager->getRepository(IntegrationImportRun::class)->findOneBy([
                'connection' => $connection,
                'type' => 'sales',
                'status' => ['queued', 'running'],
            ]);
            if ($activeRun instanceof IntegrationImportRun) {
                return $this->json([
                    'message' => $this->message($translator, 'integration.import_already_running'),
                ], Response::HTTP_CONFLICT);
            }
        }
        if (
            $normalizedSettings['salesContinuousSync']
            && (!$previousSettings['salesContinuousSync'] || $previousSettings['salesContinuousStartedAt'] === null)
        ) {
            $normalizedSettings['salesContinuousStartedAt'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        } elseif ($normalizedSettings['salesContinuousSync']) {
            $normalizedSettings['salesContinuousStartedAt'] = $previousSettings['salesContinuousStartedAt'];
        }
        $configuration['importSettings'] = $normalizedSettings;
        $database = $entityManager->getConnection();
        $database->beginTransaction();
        try {
            $connection->updateConfiguration($configuration);
            $entityManager->flush();
            if ($activateStockSync) {
                $stockOutbox->queuePublishedProductsForConnection($connection, $entityManager);
            }
            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();

            throw $exception;
        }

        return $this->json([
            'message' => $this->message($translator, 'integration.configuration_saved'),
            'connection' => $this->payload($connection),
            'importSettings' => $this->importSettings($connection),
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, EntityManagerInterface $entityManager, TranslatorInterface $translator): JsonResponse
    {
        $connection = $this->connection($id, $entityManager);
        $entityManager->remove($connection);
        $entityManager->flush();

        return $this->json(['message' => $this->message($translator, 'integration.removed')]);
    }

    private function connection(string $id, EntityManagerInterface $entityManager): IntegrationConnection
    {
        $tenant = $this->tenant($entityManager, true);
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }
        $connection = $entityManager->getRepository(IntegrationConnection::class)->findOneBy(['id' => Uuid::fromString($id), 'tenant' => $tenant]);
        if (!$connection instanceof IntegrationConnection) {
            throw $this->createNotFoundException();
        }

        return $connection;
    }

    /** @param array<string, mixed> $configuration @param array<string, string> $secrets */
    private function testConnector(string $connectorKey, array $configuration, array $secrets, ShopwareConnectionTester $shopwareTester, AnanasConnectionTester $ananasTester): void
    {
        if ($connectorKey === 'shopware') {
            $shopwareTester->test((string) $configuration['baseUrl'], $secrets);
        }
        if ($connectorKey === 'ananas') {
            $ananasTester->test($secrets);
        }
        if ($connectorKey === 'woocommerce') {
            $this->wooCommerceClient->page((string) $configuration['baseUrl'], $secrets, 'products', ['per_page' => 1]);
        }
    }

    private function connectionFailedKey(string $connectorKey): string
    {
        return match ($connectorKey) {
            'ananas' => 'integration.ananas_connection_failed',
            'woocommerce' => 'integration.woocommerce_connection_failed',
            default => 'integration.shopware_connection_failed',
        };
    }

    /** @return array{areas: array<string, bool>, productMatchOrder: list<string>} */
    private function importSettings(IntegrationConnection $connection): array
    {
        $settings = $connection->getConfiguration()['importSettings'] ?? [];
        $normalized = $this->normalizeImportSettings(
            is_array($settings) ? $settings : [],
        );
        if ($normalized['salesContinuousStartedAt'] === null) {
            $normalized['salesContinuousSync'] = false;
        }

        return $normalized;
    }

    /** @param array<string, mixed> $settings @return array{areas: array<string, bool>, productMatchOrder: list<string>} */
    private function normalizeImportSettings(array $settings): array
    {
        $areas = is_array($settings['areas'] ?? null)
            ? $settings['areas']
            : [];
        $defaults = [
            'products' => true,
            'translations' => true,
            'manufacturers' => true,
            'taxes' => true,
            'units' => true,
            'deliveryTimes' => true,
            'properties' => true,
            'tags' => true,
            'categories' => true,
            'prices' => true,
            'variants' => true,
            'media' => true,
            'customFields' => true,
            'channelPublications' => true,
            'productDownloads' => true,
            'crossSellings' => true,
            'salesCustomers' => false,
            'salesOrders' => false,
        ];
        foreach ($defaults as $key => $default) {
            $defaults[$key] = is_bool($areas[$key] ?? null)
                ? $areas[$key]
                : $default;
        }
        $defaults['products'] = true;

        $allowedMatchKeys = ['externalId', 'sku', 'ean'];
        $matchOrder = is_array($settings['productMatchOrder'] ?? null)
            ? array_values(array_filter(
                $settings['productMatchOrder'],
                static fn (mixed $item): bool => is_string($item)
                    && in_array($item, $allowedMatchKeys, true),
            ))
            : [];
        $matchOrder = array_values(array_unique($matchOrder));
        foreach ($allowedMatchKeys as $key) {
            if (!in_array($key, $matchOrder, true)) {
                $matchOrder[] = $key;
            }
        }

        return [
            'areas' => $defaults,
            'productMatchOrder' => $matchOrder,
            'salesHistoryFrom' => is_string($settings['salesHistoryFrom'] ?? null) ? $settings['salesHistoryFrom'] : null,
            'salesContinuousSync' => is_bool($settings['salesContinuousSync'] ?? null) ? $settings['salesContinuousSync'] : false,
            'salesContinuousStartedAt' => is_string($settings['salesContinuousStartedAt'] ?? null)
                ? $settings['salesContinuousStartedAt']
                : null,
            'stockAuthority' => ($settings['stockAuthority'] ?? null) === 'connect' ? 'connect' : 'shopware',
        ];
    }

    private function tenant(EntityManagerInterface $entityManager, bool $ownerRequired = false): Tenant
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $membership = $entityManager->getRepository(TenantMembership::class)->forUser($user);
        if (!$membership instanceof TenantMembership || ($ownerRequired && $membership->getRole() !== 'owner')) {
            throw $this->createAccessDeniedException();
        }

        return $membership->getTenant();
    }

    private function message(TranslatorInterface $translator, string $key): string
    {
        $user = $this->getUser();

        return $translator->trans($key, locale: $user instanceof User ? $user->getLocale() : 'bs');
    }

    /** @return array<string, mixed> */
    private function payload(IntegrationConnection $connection): array
    {
        return ['id' => $connection->getId()->toRfc4122(), 'connectorKey' => $connection->getConnectorKey(), 'name' => $connection->getName(), 'directions' => $connection->getDirections(), 'configuration' => $connection->getConfiguration(), 'status' => $connection->getStatus(), 'enabled' => $connection->isEnabled(), 'createdAt' => $connection->getCreatedAt()->format(DATE_ATOM)];
    }
}
