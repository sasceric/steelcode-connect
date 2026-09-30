<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'supplier_invoices')]
#[ORM\UniqueConstraint(name: 'uniq_supplier_invoice_number', columns: ['tenant_id', 'supplier_id', 'invoice_year', 'invoice_number'])]
#[ORM\Index(name: 'idx_supplier_invoices_tenant_created', columns: ['tenant_id', 'created_at'])]
class SupplierInvoice
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Supplier $supplier;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private PurchaseOrder $purchaseOrder;

    #[ORM\Column(length: 100)]
    private string $invoiceNumber;

    #[ORM\Column]
    private int $invoiceYear;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $invoiceDate;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 24)]
    private string $status = 'draft';

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $tax = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $shipping = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $discount = '0.0000';

    #[ORM\Column(type: 'decimal', precision: 19, scale: 4)]
    private string $declaredTotal;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'json')]
    private array $matchIssues = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $matchedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $voidedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $voidReason = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, SupplierInvoiceItem> */
    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: SupplierInvoiceItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct(Tenant $tenant, PurchaseOrder $purchaseOrder)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->purchaseOrder = $purchaseOrder;
        $this->supplier = $purchaseOrder->getSupplier();
        $this->currency = $purchaseOrder->getCurrency();
        $this->createdAt = new \DateTimeImmutable();
        $this->invoiceDate = new \DateTimeImmutable('today');
        $this->invoiceNumber = '';
        $this->invoiceYear = (int) $this->invoiceDate->format('Y');
        $this->declaredTotal = '0.0000';
        $this->items = new ArrayCollection();
    }

    public function updateDraft(
        string $number,
        \DateTimeImmutable $date,
        string $tax,
        string $shipping,
        string $discount,
        string $declaredTotal,
        ?string $note,
    ): void
    {
        if (!in_array($this->status, ['draft', 'disputed'], true)) {
            throw new \DomainException('Only draft or disputed invoices can be edited.');
        }
        $this->invoiceNumber = $number;
        $this->invoiceDate = $date;
        $this->invoiceYear = (int) $date->format('Y');
        $this->tax = $tax;
        $this->shipping = $shipping;
        $this->discount = $discount;
        $this->declaredTotal = $declaredTotal;
        $this->note = $note;
        $this->status = 'draft';
        $this->matchIssues = [];
    }

    public function addItem(PurchaseOrderItem $orderItem, string $quantity, string $unitCost): void
    {
        $this->items->add(new SupplierInvoiceItem($this, $orderItem, $quantity, $unitCost));
    }

    public function removeItem(SupplierInvoiceItem $item): void
    {
        $this->items->removeElement($item);
    }

    public function recordMatch(array $issues): void
    {
        if (!in_array($this->status, ['draft', 'disputed'], true)) {
            throw new \DomainException('This invoice cannot be matched.');
        }
        $this->matchIssues = $issues;
        $this->status = $issues === [] ? 'matched' : 'disputed';
        $this->matchedAt = $issues === [] ? new \DateTimeImmutable() : null;
    }

    public function void(string $reason): void
    {
        if ($this->status === 'void') {
            throw new \DomainException('Invoice is already void.');
        }
        $this->status = 'void';
        $this->voidReason = $reason;
        $this->voidedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    public function getPurchaseOrder(): PurchaseOrder
    {
        return $this->purchaseOrder;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function getInvoiceYear(): int
    {
        return $this->invoiceYear;
    }

    public function getInvoiceDate(): \DateTimeImmutable
    {
        return $this->invoiceDate;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTax(): string
    {
        return $this->tax;
    }

    public function getShipping(): string
    {
        return $this->shipping;
    }

    public function getDiscount(): string
    {
        return $this->discount;
    }

    public function getDeclaredTotal(): string
    {
        return $this->declaredTotal;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getMatchIssues(): array
    {
        return $this->matchIssues;
    }

    public function getMatchedAt(): ?\DateTimeImmutable
    {
        return $this->matchedAt;
    }

    public function getVoidedAt(): ?\DateTimeImmutable
    {
        return $this->voidedAt;
    }

    public function getVoidReason(): ?string
    {
        return $this->voidReason;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }
}
