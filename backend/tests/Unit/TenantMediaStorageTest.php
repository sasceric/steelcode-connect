<?php

namespace App\Tests\Unit;

use App\Entity\Media;
use App\Entity\Tenant;
use App\Service\TenantMediaStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class TenantMediaStorageTest extends TestCase
{
    public function testCurrentAndLegacyFilesCannotEscapeTenantStorage(): void
    {
        $directory = sys_get_temp_dir().'/connect-media-isolation-'.bin2hex(random_bytes(12));
        $files = new Filesystem();
        $a = new Tenant('A');
        $b = new Tenant('B');
        $storage = new TenantMediaStorage($directory);
        try {
            $files->dumpFile($directory.'/var/media/'.$a->getId().'/image.png', 'own');
            $files->dumpFile($directory.'/var/media/'.$b->getId().'/image.png', 'foreign');
            $files->dumpFile($directory.'/var/product-media/'.$a->getId().'/product/image.png', 'legacy');
            $files->symlink($directory.'/var/media/'.$b->getId().'/image.png', $directory.'/var/media/'.$a->getId().'/link.png');
            self::assertSame('own', file_get_contents($storage->path($this->media($a, $a->getId().'/image.png'), $a)));
            self::assertSame('legacy', file_get_contents($storage->pathForKey($a, 'product-media/'.$a->getId().'/product/image.png')));
            foreach ([
                $b->getId().'/image.png',
                $a->getId().'/../'.$b->getId().'/image.png',
                $a->getId().'/./image.png',
                $a->getId().'/link.png',
                $a->getId().'/missing.png',
                $a->getId().'/image.png'."\0",
                $a->getId().'\\image.png',
                '/'.$a->getId().'/image.png',
                'product-media/'.$b->getId().'/image.png',
            ] as $key) {
                try {
                    $storage->pathForKey($a, $key);
                    self::fail('Unsafe storage key accepted: '.$key);
                } catch (\DomainException) {
                    self::assertTrue(true);
                }
            }
            try {
                $storage->path($this->media($b, $a->getId().'/image.png'), $a);
                self::fail('Foreign Media ownership accepted even with an own-tenant key.');
            } catch (\DomainException) {
                self::assertTrue(true);
            }
            // The tenant directory itself must not redirect into another tenant.
            $c = new Tenant('C');
            $files->symlink($directory.'/var/media/'.$b->getId(), $directory.'/var/media/'.$c->getId());
            $this->expectException(\DomainException::class);
            $storage->pathForKey($c, $c->getId().'/image.png');
        } finally {
            $files->remove($directory);
        }
    }

    private function media(Tenant $tenant, string $key): Media
    {
        return new Media($tenant, $key, 'image.png', 'png', 3, 'image/png', null);
    }
}
