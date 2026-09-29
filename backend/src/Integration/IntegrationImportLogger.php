<?php

namespace App\Integration;

use App\Entity\IntegrationImportRun;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class IntegrationImportLogger
{
    public function __construct(
        #[Autowire(param: 'kernel.logs_dir')]
        private readonly string $logsDirectory,
    ) {
    }

    /** @param array<string, scalar|array|null> $context */
    public function info(
        IntegrationImportRun $run,
        string $stage,
        string $message,
        array $context = [],
    ): void {
        $this->write($run, 'info', $stage, $message, $context);
    }

    /** @param array<string, scalar|array|null> $context */
    public function warning(
        IntegrationImportRun $run,
        string $stage,
        string $message,
        array $context = [],
    ): void {
        $this->write($run, 'warning', $stage, $message, $context);
    }

    /** @param array<string, scalar|array|null> $context */
    public function error(
        IntegrationImportRun $run,
        string $stage,
        string $message,
        array $context = [],
    ): void {
        $this->write($run, 'error', $stage, $message, $context);
    }

    /** @param array<string, scalar|array|null> $context */
    private function write(
        IntegrationImportRun $run,
        string $level,
        string $stage,
        string $message,
        array $context,
    ): void {
        $connector = $this->connectorKey($run->getConnection()->getConnectorKey());
        $directory = rtrim($this->logsDirectory, '/').'/integrations';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('The integration log directory could not be created.');
        }

        $payload = [
            'createdAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->format(DATE_ATOM),
            'level' => $level,
            'connector' => $run->getConnection()->getConnectorKey(),
            'connectionId' => $run->getConnection()->getId()->toRfc4122(),
            'runId' => $run->getId()->toRfc4122(),
            'stage' => $stage,
            'message' => $message,
            'context' => $context,
        ];
        $logFile = sprintf('%s/%s-%s.log', $directory, $connector, date('Y-m-d'));
        $content = json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ).PHP_EOL;
        if (file_put_contents($logFile, $content, FILE_APPEND | LOCK_EX) === false) {
            throw new \RuntimeException('The integration log entry could not be written.');
        }
    }

    private function connectorKey(string $connectorKey): string
    {
        $connector = preg_replace('/[^a-z0-9_-]/', '', strtolower($connectorKey));

        return $connector === '' ? 'integration' : $connector;
    }
}
