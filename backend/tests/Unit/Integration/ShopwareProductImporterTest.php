<?php

namespace App\Tests\Unit\Integration;

use App\Integration\ShopwareProductImporter;
use PHPUnit\Framework\TestCase;

final class ShopwareProductImporterTest extends TestCase
{
    public function testItCreatesAPlainTextSummaryFromTheFirstTwoSentences(): void
    {
        $importer = (new \ReflectionClass(ShopwareProductImporter::class))
            ->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($importer, 'shortDescription');

        self::assertSame(
            'A durable everyday chair. Its steel frame is easy to clean!',
            $method->invoke(
                $importer,
                '<p>A <strong>durable</strong> everyday chair.</p><p>Its steel frame is easy to clean!</p><script>ignore()</script><p>Available in three colours.</p>',
            ),
        );
    }
}
