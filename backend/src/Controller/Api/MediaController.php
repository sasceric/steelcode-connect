<?php

namespace App\Controller\Api;

use App\Entity\Media;
use App\Entity\Tenant;
use App\Entity\TenantMembership;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/media')]
final class MediaController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): JsonResponse
    {
        $tenant = $this->tenant($entityManager);
        $media = $entityManager
            ->getRepository(Media::class)
            ->findBy(['tenant' => $tenant], ['createdAt' => 'DESC']);

        return $this->json([
            'media' => array_map($this->payload(...), $media),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function upload(
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $tenant = $this->tenant($entityManager, true);
        $file = $request->files->get('file');
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
        ];

        if (
            !$file instanceof UploadedFile
            || !$file->isValid()
            || $file->getSize() > 10 * 1024 * 1024
            || !isset($allowedMimeTypes[$file->getMimeType() ?? ''])
        ) {
            return $this->json(['message' => 'Invalid image.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $size = $file->getSize();
        $mimeType = $file->getMimeType();
        $extension = $allowedMimeTypes[$mimeType];
        $name = trim($file->getClientOriginalName()) ?: 'image.'.$extension;
        $directory = $this->getParameter('kernel.project_dir').'/var/media/'.$tenant->getId()->toRfc4122();

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $storedName = Uuid::v7()->toRfc4122().'.'.$extension;
        $file->move($directory, $storedName);

        $media = new Media(
            $tenant,
            $tenant->getId()->toRfc4122().'/'.$storedName,
            $name,
            $extension,
            $size ?: null,
            $mimeType,
            hash_file('sha256', $directory.'/'.$storedName) ?: null,
        );
        $entityManager->persist($media);
        $entityManager->flush();

        return $this->json([
            'media' => $this->payload($media),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/file', methods: ['GET'])]
    public function download(
        string $id,
        EntityManagerInterface $entityManager,
    ): BinaryFileResponse {
        $tenant = $this->tenant($entityManager);
        $media = $this->media($id, $tenant, $entityManager);
        $storageKey = $media->getStorageKey();
        $projectDirectory = $this->getParameter('kernel.project_dir');
        $path = str_starts_with($storageKey, 'product-media/')
            ? $projectDirectory.'/var/'.substr($storageKey, 0)
            : $projectDirectory.'/var/media/'.$storageKey;

        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        return new BinaryFileResponse($path);
    }

    private function media(
        string $id,
        Tenant $tenant,
        EntityManagerInterface $entityManager,
    ): Media {
        if (!Uuid::isValid($id)) {
            throw $this->createNotFoundException();
        }

        $media = $entityManager
            ->getRepository(Media::class)
            ->findOneBy([
                'id' => Uuid::fromString($id),
                'tenant' => $tenant,
            ]);

        if (!$media instanceof Media) {
            throw $this->createNotFoundException();
        }

        return $media;
    }

    private function tenant(
        EntityManagerInterface $entityManager,
        bool $ownerRequired = false,
    ): Tenant {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $membership = $entityManager
            ->getRepository(TenantMembership::class)
            ->findOneBy(['user' => $user]);

        if (
            !$membership instanceof TenantMembership
            || ($ownerRequired && $membership->getRole() !== 'owner')
        ) {
            throw $this->createAccessDeniedException();
        }

        return $membership->getTenant();
    }

    private function payload(Media $media): array
    {
        return [
            'id' => $media->getId()->toRfc4122(),
            'name' => $media->getFileName(),
            'url' => '/api/v1/media/'.$media->getId()->toRfc4122().'/file',
        ];
    }
}
