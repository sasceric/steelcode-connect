<?php

namespace App\Tests\Unit\Integration;

use App\Entity\IntegrationConnection;
use App\Entity\IntegrationImportRun;
use App\Entity\Tenant;
use App\Integration\IntegrationImportLogger;
use App\Integration\IntegrationImportLogReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class IntegrationLogTenantScopeTest extends TestCase
{
    public function testLogsAreNamespacedAndLegacyReadsRequireExactOwnership(): void
    {
        $directory = sys_get_temp_dir().'/connect-log-isolation-'.bin2hex(random_bytes(12));
        $files = new Filesystem();
        try {
            $a = new Tenant('Log A');
            $b = new Tenant('Log B');
            $connectionA = new IntegrationConnection($a, 'shopware', 'A', ['source'], []);
            $connectionB = new IntegrationConnection($b, 'shopware', 'B', ['source'], []);
            $runA = new IntegrationImportRun($a, $connectionA, 'sales');
            $runB = new IntegrationImportRun($b, $connectionB, 'sales');
            $logger = new IntegrationImportLogger($directory);
            $reader = new IntegrationImportLogReader($directory);
            $logger->info($runA, 'sales', 'Company A', ['count' => 1]);
            $logger->info($runB, 'sales', 'Company B');
            foreach ([$runA, $runB] as $run) {
                $paths = glob($directory.'/integrations/'.$run->getTenant()->getId().'/shopware-*.log');
                self::assertCount(1, $paths);
                $entry = json_decode(trim(file_get_contents($paths[0])), true, flags: JSON_THROW_ON_ERROR);
                self::assertSame((string) $run->getTenant()->getId(), $entry['tenantId']);
            }
            // Historical flat logs have no tenant claim; run + connection must both match.
            $legacy = [
                'createdAt' => '2026-10-01T00:00:00Z', 'level' => 'info', 'stage' => 'sales',
                'connectionId' => (string) $connectionA->getId(), 'runId' => (string) $runA->getId(),
                'message' => 'Legacy A',
            ];
            $records = [$legacy];
            $records[] = array_replace($legacy, ['tenantId' => (string) $b->getId(), 'message' => 'Wrong tenant claim']);
            $records[] = array_replace($legacy, ['tenantId' => 42, 'message' => 'Invalid tenant claim']);
            $records[] = array_replace($legacy, ['connectionId' => (string) $connectionB->getId(), 'message' => 'Wrong connection']);
            $records[] = array_replace($legacy, ['runId' => (string) $runB->getId(), 'message' => 'Wrong run']);
            $files->dumpFile($directory.'/integrations/shopware-2026-10-01.log', implode("\n", array_map(
                static fn (array $record): string => json_encode($record, JSON_THROW_ON_ERROR),
                $records,
            ))."\n");
            self::assertSame(['Legacy A', 'Company A'], array_column($reader->forRun($runA), 'message'));
            self::assertSame(['Company B'], array_column($reader->forRun($runB), 'message'));
            self::assertSame(['count' => 1], $reader->forRun($runA)[1]['context']);
            $invalid = new IntegrationImportRun($a, $connectionB, 'sales');
            foreach ([
                static fn () => $logger->info($invalid, 'sales', 'Invalid'),
                static fn () => $reader->forRun($invalid),
            ] as $operation) {
                try {
                    $operation();
                    self::fail('Inconsistent run ownership must be rejected.');
                } catch (\DomainException) {
                    self::assertTrue(true);
                }
            }
        } finally {
            $files->remove($directory);
        }
    }
}
