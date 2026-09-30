<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'purchase_receipts')]
#[ORM\Index(name: 'idx_purchase_receipts_order_created', columns: ['purchase_order_id', 'created_at'])]
class PurchaseReceipt
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PurchaseOrder $purchaseOrder;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $receiptKey;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private PurchaseOrderItem $item;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $goodQuantity;

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $damagedQuantity;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note;

    #[ORM\Column(length: 24, nullable: true)]
    private ?string $damageResolution = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $damageResolutionNote = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $damageResolvedAt = null;

    #[ORM\Column(type: 'json')]
    private array $damageHistory = [];

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $quarantineQuantity = '0.0000';

    #[ORM\Column(length: 24, nullable: true)]
    private ?string $quarantineStatus = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        PurchaseOrder $purchaseOrder,
        Uuid $receiptKey,
        PurchaseOrderItem $item,
        string $goodQuantity,
        string $damagedQuantity,
        ?User $user,
        ?string $note,
        string $quarantineQuantity = '0.0000',
    )
    {
        $this->id = Uuid::v7();
        $this->purchaseOrder = $purchaseOrder;
        $this->receiptKey = $receiptKey;
        $this->item = $item;
        $this->goodQuantity = $goodQuantity;
        $this->damagedQuantity = $damagedQuantity;
        if ((float) $damagedQuantity > 0) {
            $this->damageResolution = 'open';
            $this->quarantineQuantity = $quarantineQuantity;
            $this->quarantineStatus = (float) $quarantineQuantity > 0 ? 'held' : 'legacy_untracked';
        }
        $this->user = $user;
        $this->note = $note;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getItem(): PurchaseOrderItem
    {
        return $this->item;
    }

    public function getReceiptKey(): ?Uuid
    {
        return $this->receiptKey;
    }

    public function getGoodQuantity(): string
    {
        return $this->goodQuantity;
    }

    public function getDamagedQuantity(): string
    {
        return $this->damagedQuantity;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDamageResolution(): ?string
    {
        return $this->damageResolution;
    }

    public function getDamageResolutionNote(): ?string
    {
        return $this->damageResolutionNote;
    }

    public function getDamageResolvedAt(): ?\DateTimeImmutable
    {
        return $this->damageResolvedAt;
    }

    public function getDamageHistory(): array
    {
        return $this->damageHistory;
    }

    public function getQuarantineQuantity(): string
    {
        return $this->quarantineQuantity;
    }

    public function getQuarantineStatus(): ?string
    {
        return $this->quarantineStatus;
    }

    public function resolveQuarantine(string $disposition, ?string $note, ?User $user = null): void
    {
        if ($this->quarantineStatus !== 'held') {
            throw new \DomainException('Only physically held damaged goods can be released, returned, or scrapped.');
        }
        if (!in_array($disposition, ['released', 'returned', 'scrapped'], true)) {
            throw new \DomainException('Invalid quarantine disposition.');
        }
        $this->damageHistory[] = [
            'kind' => 'physical',
            'from' => 'held',
            'to' => $disposition,
            'note' => $note,
            'userId' => $user?->getId()->toRfc4122(),
            'at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
        $this->quarantineStatus = $disposition;
    }

    public function resolveDamage(string $resolution, ?string $note, ?User $user = null): void
    {
        if ((float) $this->damagedQuantity <= 0) {
            throw new \DomainException('This receipt has no damaged goods.');
        }
        if (!in_array($resolution, ['open', 'returned', 'credited', 'written_off', 'replaced'], true)) {
            throw new \DomainException('Invalid damage resolution.');
        }
        $this->damageHistory[] = [
            'kind' => 'claim',
            'from' => $this->damageResolution,
            'to' => $resolution,
            'note' => $note,
            'userId' => $user?->getId()->toRfc4122(),
            'at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
        $this->damageResolution = $resolution;
        $this->damageResolutionNote = $note;
        $this->damageResolvedAt = $resolution === 'open' ? null : new \DateTimeImmutable();
    }
}
