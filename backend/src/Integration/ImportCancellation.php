<?php

namespace App\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final class ImportCancellation
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function throwIfCancelled(Uuid $runId): void
    {
        $status = $this->connection->fetchOne(
            'SELECT status FROM integration_import_runs WHERE id = :id',
            ['id' => $runId->toRfc4122()],
        );

        if ($status === 'cancelled') {
            throw new ImportCancelledException('The import was cancelled.');
        }
    }
}
