<?php

namespace App\Http;

use App\Entity\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/** The same private response policy applies to session and capability downloads. */
final class PrivateMediaResponse
{
    public static function inline(string $path, Media $media): BinaryFileResponse
    {
        $response = new BinaryFileResponse($path, public: false);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $media->getFileName());
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Content-Type', $media->getMimeType() ?? 'application/octet-stream');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");

        return $response;
    }
}
