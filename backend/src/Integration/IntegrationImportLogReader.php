<?php

namespace App\Integration;

use App\Entity\IntegrationImportRun;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class IntegrationImportLogReader
{
    public function __construct(
        #[Autowire(param: 'kernel.logs_dir')]
        private readonly string $logsDirectory,
    ) {
    }

    /**
     * @return list<array{
     *     id: string,
     *     level: string,
     *     stage: string,
     *     message: string,
     *     context: array<string, mixed>,
     *     createdAt: string
     * }>
     */
    public function forRun(IntegrationImportRun $run, int $limit = 250): array
    {
        if (!$run->getTenant()->getId()->equals($run->getConnection()->getTenant()->getId())) {
            throw new \DomainException('The integration log has inconsistent tenant ownership.');
        }
        $connector = $this->connectorKey($run->getConnection()->getConnectorKey());
        $directory = rtrim($this->logsDirectory, '/').'/integrations';
        $files = [
            ...(glob($directory.'/'.$run->getTenant()->getId().'/'.$connector.'-*.log') ?: []),
            // Historical shared log files remain readable only by exact run and connection.
            ...(glob($directory.'/'.$connector.'-*.log') ?: []),
        ];
        usort(
            $files,
            static fn (string $left, string $right): int => filemtime($right) <=> filemtime($left),
        );

        $entries = [];
        foreach (array_slice($files, 0, 31) as $file) {
            $entries = [...$entries, ...$this->entriesInFile($file, $run)];
        }
        usort(
            $entries,
            static fn (array $left, array $right): int => $left['createdAt'] <=> $right['createdAt'],
        );

        return array_slice($entries, -$limit);
    }

    /**
     * @return list<array{
     *     id: string,
     *     level: string,
     *     stage: string,
     *     message: string,
     *     context: array<string, mixed>,
     *     createdAt: string
     * }>
     */
    private function entriesInFile(string $path, IntegrationImportRun $run): array
    {
        $file = new \SplFileObject($path, 'r');
        $entries = [];
        $lineNumber = 0;
        while (!$file->eof()) {
            ++$lineNumber;
            $entry = $this->parseEntry(trim($file->fgets()), $path, $lineNumber);
            if ($entry === null || $entry['runId'] !== $run->getId()->toRfc4122()) {
                continue;
            }
            if ($entry['connectionId'] !== $run->getConnection()->getId()->toRfc4122()) {
                continue;
            }
            if ($entry['tenantId'] !== null && $entry['tenantId'] !== $run->getTenant()->getId()->toRfc4122()) {
                continue;
            }

            $entries[] = [
                'id' => $entry['id'],
                'level' => $entry['level'],
                'stage' => $entry['stage'],
                'message' => $entry['message'],
                'context' => $entry['context'],
                'createdAt' => $entry['createdAt'],
            ];
        }

        return $entries;
    }

    /**
     * @return array{
     *     id: string,
     *     level: string,
     *     stage: string,
     *     message: string,
     *     context: array<string, mixed>,
     *     createdAt: string,
     *     connectionId: string,
     *     tenantId: ?string,
     *     runId: string
     * }|null
     */
    private function parseEntry(string $line, string $path, int $lineNumber): ?array
    {
        if ($line === '') {
            return null;
        }

        $payload = json_decode($line, true);
        if (is_array($payload)) {
            return $this->payloadEntry($payload, $path, $lineNumber);
        }

        return $this->legacyEntry($line, $path, $lineNumber);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{
     *     id: string,
     *     level: string,
     *     stage: string,
     *     message: string,
     *     context: array<string, mixed>,
     *     createdAt: string,
     *     connectionId: string,
     *     tenantId: ?string,
     *     runId: string
     * }|null
     */
    private function payloadEntry(array $payload, string $path, int $lineNumber): ?array
    {
        $context = is_array($payload['context'] ?? null) ? $payload['context'] : [];
        $createdAt = $this->string($payload['createdAt'] ?? $payload['datetime'] ?? null);
        $connectionId = $this->string($payload['connectionId'] ?? $context['connectionId'] ?? null);
        $tenantId = $this->string($payload['tenantId'] ?? $context['tenantId'] ?? null);
        if ($tenantId === null && (array_key_exists('tenantId', $payload) || array_key_exists('tenantId', $context))) {
            return null;
        }
        $runId = $this->string($payload['runId'] ?? $context['runId'] ?? null);
        $stage = $this->string($payload['stage'] ?? $context['stage'] ?? null);
        $message = $this->string($payload['message'] ?? null);
        $level = $this->string($payload['level'] ?? $payload['level_name'] ?? null);
        if (
            $createdAt === null
            || $connectionId === null
            || $runId === null
            || $stage === null
            || $message === null
            || $level === null
        ) {
            return null;
        }

        return [
            'id' => sha1($path.':'.$lineNumber),
            'level' => strtolower($level),
            'stage' => $stage,
            'message' => $message,
            'context' => $this->details($payload, $context),
            'createdAt' => $createdAt,
            'connectionId' => $connectionId,
            'tenantId' => $tenantId,
            'runId' => $runId,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     level: string,
     *     stage: string,
     *     message: string,
     *     context: array<string, mixed>,
     *     createdAt: string,
     *     connectionId: string,
     *     tenantId: ?string,
     *     runId: string
     * }|null
     */
    private function legacyEntry(string $line, string $path, int $lineNumber): ?array
    {
        $matched = preg_match(
            '/^\[(?<createdAt>[^\]]+)]\s+[^.]+\.(?<level>[A-Z]+):\s+(?<message>.*?)\s+(?<context>{.*})\s+\[\]$/',
            $line,
            $matches,
        );
        if ($matched !== 1) {
            return null;
        }

        $context = json_decode($matches['context'], true);
        if (!is_array($context)) {
            return null;
        }

        return $this->payloadEntry([
            'createdAt' => $matches['createdAt'],
            'level' => $matches['level'],
            'message' => $matches['message'],
            'context' => $context,
        ], $path, $lineNumber);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function details(array $payload, array $context): array
    {
        if (is_array($payload['context'] ?? null) && isset($payload['context']['details'])) {
            return is_array($payload['context']['details'])
                ? $payload['context']['details']
                : [];
        }

        unset($context['connector'], $context['tenantId'], $context['connectionId'], $context['runId'], $context['stage']);

        return $context;
    }

    private function connectorKey(string $connectorKey): string
    {
        $connector = preg_replace('/[^a-z0-9_-]/', '', strtolower($connectorKey));

        return $connector === '' ? 'integration' : $connector;
    }

    private function string(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }
}
