<?php

/** Read-only live comparison against the acknowledged, resolved owned payload. */

use App\Entity\IntegrationConnection;
use App\Integration\CatalogueExportReferences;
use App\Integration\SecretCipher;
use App\Integration\ShopwareClient;
use App\Integration\WooCommerceClient;
use App\Kernel;
use App\MessageHandler\ExportShopwareCatalogueHandler;
use App\MessageHandler\ExportWooCommerceCatalogueHandler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Uid\Uuid;

require dirname(__DIR__).'/bootstrap.php';

[$script, $tenantId, $runId] = array_pad($argv, 3, '');
if (!Uuid::isValid($tenantId) || !Uuid::isValid($runId)) {
    throw new RuntimeException('An explicit tenant and completed export run are required.');
}
$kernel = new Kernel('dev', false);
$kernel->boot();
$manager = $kernel->getContainer()->get('doctrine')->getManager();
$database = $manager->getConnection();
$plan = $database->fetchAssociative(
    "SELECT p.* FROM integration_export_plans p JOIN integration_import_runs r ON r.id = p.run_id AND r.tenant_id = p.tenant_id AND r.connection_id = p.connection_id WHERE p.tenant_id = :tenant AND p.run_id = :run AND p.status = 'completed' AND r.status = 'completed'",
    ['tenant' => $tenantId, 'run' => $runId],
);
if (!$plan) {
    throw new RuntimeException('Completed plan not found in this tenant.');
}
$connection = $manager->getRepository(IntegrationConnection::class)->findOneBy([
    'tenant' => $tenantId, 'id' => $plan['connection_id'],
]);
$provider = $connection->getConnectorKey();
$url = rtrim($connection->getConfiguration()['baseUrl'] ?? '', '/');
if (!in_array([$provider, $url], [
    ['shopware', 'http://shopware67.test'], ['woocommerce', 'http://wp-test.test'],
], true)) {
    throw new RuntimeException('Only the approved local demo destinations may be read.');
}
$http = HttpClient::create();
$shopware = new ShopwareClient($http);
$woo = new WooCommerceClient($http);
$references = new CatalogueExportReferences($shopware, new SecretCipher($kernel->getContainer()->getParameter('kernel.secret')));
$secrets = $references->credentials($connection, $manager);
$items = $database->fetchAllAssociative(<<<'SQL'
SELECT i.external_id, i.parent_id, b.owned_payload
FROM integration_export_items i
JOIN integration_catalogue_publications b ON b.product_id = i.product_id AND b.tenant_id = :tenant AND b.connection_id = :connection
WHERE i.plan_id = :plan AND i.status = 'published' ORDER BY i.product_id
SQL, ['tenant' => $tenantId, 'connection' => $plan['connection_id'], 'plan' => $plan['id']]);
$verified = 0;
foreach ($items as $item) {
    $payload = json_decode($item['owned_payload'], true, flags: JSON_THROW_ON_ERROR);
    if ($provider === 'shopware') {
        // Reuse the exporter's JSON:API conversion and canonical hash rather
        // than introducing another provider normalization implementation.
        $associations = new ReflectionMethod(ExportShopwareCatalogueHandler::class, 'productAssociations');
        $conversion = new ReflectionMethod(ExportShopwareCatalogueHandler::class, 'attributes');
        $response = $shopware->searchPage($url, $secrets, 'product', [
            'ids' => [$item['external_id']], 'limit' => 1, 'associations' => $associations->invoke(null),
        ]);
        $target = isset($response['data'][0]) ? $conversion->invoke(null, $response['data'][0], $response['included'] ?? []) : [];
        $class = ExportShopwareCatalogueHandler::class;
    } else {
        $resource = 'products/'.$item['external_id'];
        if ($item['parent_id'] !== null) {
            $parent = $database->fetchOne(
                "SELECT external_id FROM integration_entity_mappings WHERE tenant_id = :tenant AND connection_id = :connection AND entity_type = 'product' AND local_id = :parent",
                ['tenant' => $tenantId, 'connection' => $plan['connection_id'], 'parent' => $item['parent_id']],
            );
            $resource = 'products/'.$parent.'/variations/'.$item['external_id'];
        }
        $target = $woo->object('GET', $url, $secrets, $resource);
        $class = ExportWooCommerceCatalogueHandler::class;
    }
    if ($payload === [] || $class::targetHash($target, $payload) !== $class::targetHash($payload, $payload)) {
        throw new RuntimeException('A live owned payload differs from its acknowledged publication: '.$item['external_id']);
    }
    ++$verified;
}
if ($verified === 0) {
    throw new RuntimeException('No published items were verified.');
}
echo json_encode(['provider' => $provider, 'runId' => $runId, 'liveOwnedPayloadsVerified' => $verified], JSON_THROW_ON_ERROR).PHP_EOL;
$kernel->shutdown();
