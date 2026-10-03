<?php

namespace App\Tests\Integration;

use App\Controller\Api\CatalogueMediaDeliveryController;
use App\Entity\IntegrationConnection;
use App\Entity\Media;
use App\Entity\Tenant;
use App\Integration\CatalogueMediaDelivery;
use App\Service\TenantMediaStorage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Filesystem\Filesystem;

final class CatalogueMediaDeliveryScopeTest extends KernelTestCase
{
    public function testDeliveryRequiresCurrentTenantConnectionAndImmutableMediaSnapshot(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get('doctrine')->getManager();
        $database = $manager->getConnection();
        $database->beginTransaction();
        $path = null;
        try {
            $tenant = new Tenant('Delivery scope');
            $other = new Tenant('Foreign delivery scope');
            $connection = new IntegrationConnection($tenant, 'woocommerce', 'Delivery', ['channel'], []);
            $connection->activate('Fixture');
            $key = 'delivery-scope-'.bin2hex(random_bytes(8)).'.svg';
            $project = dirname(__DIR__, 2);
            $path = $project.'/var/media/'.$tenant->getId().'/'.$key;
            $contents = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>';
            (new Filesystem())->dumpFile($path, $contents);
            $media = new Media($tenant, $tenant->getId().'/'.$key, $key, 'svg', strlen($contents), 'image/svg+xml', hash('sha256', $contents));
            foreach ([$tenant, $other, $connection, $media] as $entity) {
                $manager->persist($entity);
            }
            $manager->flush();
            $delivery = new CatalogueMediaDelivery('delivery-test-secret', 'https://connect.example.test');
            parse_str(parse_url($delivery->url($connection, $media), PHP_URL_QUERY), $query);
            $request = new Request($query);
            $services = new Container();
            $services->setParameter('kernel.project_dir', $project);
            $services->set('parameter_bag', new ContainerBag($services));
            $controller = new CatalogueMediaDeliveryController();
            $controller->setContainer($services);
            $storage = new TenantMediaStorage($project);
            $response = $controller->download((string) $media->getId(), $key, $request, $delivery, $manager, $storage);
            self::assertSame($path, $response->getFile()->getPathname());
            self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
            self::assertSame("default-src 'none'; sandbox", $response->headers->get('Content-Security-Policy'));
            self::assertTrue($response->headers->hasCacheControlDirective('private'));
            self::assertTrue($response->headers->hasCacheControlDirective('no-store'));

            $claims = $delivery->verify($query['capability']);
            $claims['tenant'] = (string) $other->getId();
            $encoded = rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
            $forgedScope = $encoded.'.'.hash_hmac('sha256', 'catalogue-media:'.$encoded, 'delivery-test-secret');
            foreach ([
                new Request(['capability' => $forgedScope]),
                new Request(['capability' => $query['capability'].'tampered']),
            ] as $invalid) {
                try {
                    $controller->download((string) $media->getId(), $key, $invalid, $delivery, $manager, $storage);
                    self::fail('Invalid capability scope returned media.');
                } catch (NotFoundHttpException) {
                    self::assertTrue(true);
                }
            }
            $database->executeStatement(
                'UPDATE media SET checksum = :checksum WHERE tenant_id = :tenant AND id = :media',
                ['checksum' => str_repeat('a', 64), 'tenant' => (string) $tenant->getId(), 'media' => (string) $media->getId()],
            );
            $manager->clear();
            $this->expectException(NotFoundHttpException::class);
            $controller->download((string) $media->getId(), $key, $request, $delivery, $manager, $storage);
        } finally {
            $database->rollBack();
            $manager->clear();
            if ($path !== null && is_file($path)) {
                unlink($path);
                rmdir(dirname($path));
            }
        }
    }
}
