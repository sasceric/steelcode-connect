<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'sales_pick_tasks')]
#[ORM\Index(name: 'idx_sales_pick_task_order_created', columns: ['sales_order_id', 'created_at'])]
class SalesPickTask
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SalesOrder $salesOrder;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Warehouse $warehouse;

    #[ORM\Column(length: 24)]
    private string $status = 'open';

    /** @var list<array<string, string>> */
    #[ORM\Column(type: 'json')]
    private array $lines;

    /** @var array<string, string> */
    #[ORM\Column(type: 'json')]
    private array $pickedQuantities = [];

    #[ORM\Column]
    private int $version = 1;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $createdBy;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        SalesOrder $salesOrder,
        Warehouse $warehouse,
        User $createdBy,
        array $lines,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $salesOrder->getTenant();
        $this->salesOrder = $salesOrder;
        $this->warehouse = $warehouse;
        $this->createdBy = $createdBy;
        $this->lines = $lines;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSalesOrder(): SalesOrder
    {
        return $this->salesOrder;
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /** @return list<array<string, string>> */
    public function getLines(): array
    {
        return $this->lines;
    }

    /** @return array<string, string> */
    public function getPickedQuantities(): array
    {
        return $this->pickedQuantities;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @param array<string, string> $quantities */
    public function record(array $quantities, bool $complete, User $user): void
    {
        if (!in_array($this->status, ['open', 'in_progress'], true)) {
            throw new \DomainException('This pick task cannot be changed.');
        }

        $this->pickedQuantities = $quantities;
        $this->status = 'in_progress';
        if ($complete) {
            $short = false;
            foreach ($this->lines as $line) {
                if ((float) $quantities[$line['itemId']] + 0.00001 < (float) $line['quantity']) {
                    $short = true;
                    break;
                }
            }
            $this->status = $short ? 'short' : 'picked';
        }

        $this->updatedBy = $user;
        $this->updatedAt = new \DateTimeImmutable();
        ++$this->version;
    }

    public function markStale(): void
    {
        if (in_array($this->status, ['open', 'in_progress'], true)) {
            $this->status = 'stale';
            $this->updatedAt = new \DateTimeImmutable();
            ++$this->version;
        }
    }
}
