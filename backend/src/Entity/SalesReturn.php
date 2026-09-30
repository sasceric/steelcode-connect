<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'sales_returns')]
#[ORM\UniqueConstraint(name: 'uniq_sales_return_request', columns: ['tenant_id', 'request_id'])]
#[ORM\Index(name: 'idx_sales_return_order', columns: ['sales_order_id', 'created_at'])]
class SalesReturn
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private SalesOrder $salesOrder;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private SalesOrderItem $salesOrderItem;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Warehouse $warehouse;

    #[ORM\Column(type: 'uuid')]
    private Uuid $requestId;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $quantity;

    #[ORM\Column(length: 64)]
    private string $reason;

    #[ORM\Column(length: 24)]
    private string $condition;

    #[ORM\Column(length: 24)]
    private string $receivedCondition;

    #[ORM\Column(length: 24)]
    private string $disposition;

    #[ORM\Column(length: 24)]
    private string $initialDisposition;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $receivedBy;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $resolvedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct(
        SalesOrderItem $item,
        Warehouse $warehouse,
        Uuid $requestId,
        string $quantity,
        string $reason,
        string $condition,
        string $disposition,
        ?string $note,
        User $receivedBy,
    ) {
        $this->id = Uuid::v7();
        $this->salesOrder = $item->getSalesOrder();
        $this->tenant = $this->salesOrder->getTenant();
        $this->salesOrderItem = $item;
        $this->warehouse = $warehouse;
        $this->requestId = $requestId;
        $this->quantity = $quantity;
        $this->reason = $reason;
        $this->condition = $condition;
        $this->receivedCondition = $condition;
        $this->disposition = $disposition;
        $this->initialDisposition = $disposition;
        $this->note = $note;
        $this->receivedBy = $receivedBy;
        $this->createdAt = new \DateTimeImmutable();
        if ($disposition !== 'quarantine') {
            $this->resolvedBy = $receivedBy;
            $this->resolvedAt = $this->createdAt;
        }
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRequestId(): Uuid
    {
        return $this->requestId;
    }

    public function getSalesOrder(): SalesOrder
    {
        return $this->salesOrder;
    }

    public function getSalesOrderItem(): SalesOrderItem
    {
        return $this->salesOrderItem;
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getCondition(): string
    {
        return $this->condition;
    }

    public function getReceivedCondition(): string
    {
        return $this->receivedCondition;
    }

    public function getDisposition(): string
    {
        return $this->disposition;
    }

    public function getInitialDisposition(): string
    {
        return $this->initialDisposition;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function resolve(string $disposition, string $condition, User $user): void
    {
        if ($this->disposition !== 'quarantine') {
            throw new \DomainException('Only quarantined returns can be resolved.');
        }
        if (!in_array($disposition, ['restock', 'write_off'], true)) {
            throw new \InvalidArgumentException('Choose restock or write-off.');
        }

        $this->disposition = $disposition;
        $this->condition = $condition;
        $this->resolvedBy = $user;
        $this->resolvedAt = new \DateTimeImmutable();
    }
}
