<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'integration_import_runs')]
#[ORM\Index(
    name: 'idx_integration_import_run_connection_created',
    columns: ['connection_id', 'created_at'],
)]
class IntegrationImportRun
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private IntegrationConnection $connection;

    #[ORM\Column(length: 64)]
    private string $type;

    #[ORM\Column(length: 16)]
    private string $status = 'queued';

    #[ORM\Column(length: 64)]
    private string $currentStage = 'queued';

    #[ORM\Column]
    private int $totalItems = 0;

    #[ORM\Column]
    private int $processedItems = 0;

    #[ORM\Column]
    private int $createdItems = 0;

    #[ORM\Column]
    private int $updatedItems = 0;

    #[ORM\Column]
    private int $failedItems = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct(
        Tenant $tenant,
        IntegrationConnection $connection,
        string $type,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->connection = $connection;
        $this->type = $type;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getConnection(): IntegrationConnection
    {
        return $this->connection;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCurrentStage(): string
    {
        return $this->currentStage;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getProcessedItems(): int
    {
        return $this->processedItems;
    }

    public function getCreatedItems(): int
    {
        return $this->createdItems;
    }

    public function getUpdatedItems(): int
    {
        return $this->updatedItems;
    }

    public function getFailedItems(): int
    {
        return $this->failedItems;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function start(int $totalItems, string $stage = 'products'): void
    {
        $this->status = 'running';
        $this->totalItems = max(0, $totalItems);
        $this->currentStage = $stage;
        $this->startedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setCurrentStage(string $stage): void
    {
        $this->currentStage = mb_substr($stage, 0, 64);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateTotalItems(int $totalItems): void
    {
        $this->totalItems = max($this->processedItems, $totalItems);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function recordSuccess(bool $created): void
    {
        ++$this->processedItems;
        if ($created) {
            ++$this->createdItems;
        } else {
            ++$this->updatedItems;
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function recordFailure(): void
    {
        ++$this->processedItems;
        ++$this->failedItems;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function complete(): void
    {
        $this->status = 'completed';
        $this->currentStage = 'completed';
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        if (!in_array($this->status, ['queued', 'running'], true)) {
            return;
        }

        $this->status = 'cancelled';
        $this->currentStage = 'cancelled';
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function fail(string $reason): void
    {
        $this->status = 'failed';
        $this->currentStage = 'failed';
        $this->failureReason = mb_substr($reason, 0, 1000);
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }
}
