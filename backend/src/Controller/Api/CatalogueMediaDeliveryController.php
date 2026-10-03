<?php

namespace App\Controller\Api;

use App\Entity\IntegrationConnection;
use App\Entity\Media;
use App\Http\PrivateMediaResponse;
use App\Integration\CatalogueMediaDelivery;
use App\Service\TenantMediaStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class CatalogueMediaDeliveryController extends AbstractController
{
    #[Route('/api/v1/catalogue-media/{id}/{filename}', methods: ['GET'])]
    public function download(
        string $id,
        string $filename,
        Request $request,
        CatalogueMediaDelivery $delivery,
        EntityManagerInterface $manager,
        TenantMediaStorage $storage,
    ): BinaryFileResponse
    {
        try {
            $claims = $delivery->verify($request->query->getString('capability'));
            if (($claims['media'] ?? null) !== $id) {
                throw new \DomainException('Invalid media identity.');
            }
            foreach (['tenant', 'connection', 'media'] as $key) {
                if (!Uuid::isValid($claims[$key] ?? '')) {
                    throw new \DomainException('Invalid media scope.');
                }
            }
        } catch (\Throwable $exception) {
            throw $this->createNotFoundException('Invalid or expired media capability.', $exception);
        }
        $connection = $manager->getRepository(IntegrationConnection::class)->findOneBy([
            'id' => $claims['connection'], 'tenant' => $claims['tenant'],
        ]);
        $media = $manager->getRepository(Media::class)->findOneBy([
            'id' => $claims['media'], 'tenant' => $claims['tenant'],
        ]);
        if (!$connection instanceof IntegrationConnection || !$media instanceof Media
            || !$connection->isEnabled() || $connection->getStatus() !== 'active'
            || !in_array('channel', $connection->getDirections(), true)
            || $media->getChecksum() !== $claims['checksum']
            || $media->getFileName() !== $filename
            || !str_starts_with($media->getMimeType() ?? '', 'image/')) {
            throw $this->createNotFoundException('Media capability scope or snapshot no longer matches.');
        }
        try {
            $path = $storage->path($media, $connection->getTenant());
        } catch (\DomainException $exception) {
            throw $this->createNotFoundException('The media file is not available.', $exception);
        }
        return PrivateMediaResponse::inline($path, $media);
    }
}
