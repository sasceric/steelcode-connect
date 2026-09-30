<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'integration_sales_sync_cursors')]
#[ORM\UniqueConstraint(name: 'uniq_sales_sync_connection', columns: ['connection_id'])]
final class IntegrationSalesSyncCursor
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private IntegrationConnection $connection;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSyncedAt;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $lastPendingOrderId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastErrorAt = null;

    public function __construct(IntegrationConnection $connection, \DateTimeImmutable $startedAt)
    {
        $this->id = Uuid::v7();
        $this->connection = $connection;
        $this->startedAt = $startedAt;
        $this->lastSyncedAt = $startedAt;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function restart(\DateTimeImmutable $startedAt): void
    {
        $this->startedAt = $startedAt;
        $this->lastSyncedAt = $startedAt;
        $this->lastPendingOrderId = null;
        $this->lastError = null;
        $this->lastErrorAt = null;
    }

    public function getLastPendingOrderId(): ?string
    {
        return $this->lastPendingOrderId;
    }

    public function markPendingOrderId(?string $id): void
    {
        $this->lastPendingOrderId = $id;
    }

    public function getLastSyncedAt(): \DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getLastErrorAt(): ?\DateTimeImmutable
    {
        return $this->lastErrorAt;
    }

    public function fail(string $message): void
    {
        $this->lastError = mb_substr($message, 0, 1000);
        $this->lastErrorAt = new \DateTimeImmutable();
    }

    public function advance(\DateTimeImmutable $syncedAt): void
    {
        if ($syncedAt > $this->lastSyncedAt) {
            $this->lastSyncedAt = $syncedAt;
        }
        $this->lastError = null;
        $this->lastErrorAt = null;
    }
}
