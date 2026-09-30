<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'purchase_orders')]
#[ORM\Index(name: 'idx_purchase_orders_tenant_created', columns: ['tenant_id', 'created_at'])]
class PurchaseOrder
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
    private Warehouse $warehouse;

    #[ORM\Column(length: 24)]
    private string $status = 'draft';

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $supplierSnapshot = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastEmailedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastEmailedTo = null;

    /** @var Collection<int, PurchaseOrderItem> */
    #[ORM\OneToMany(mappedBy: 'purchaseOrder', targetEntity: PurchaseOrderItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    public function __construct(Tenant $tenant, Supplier $supplier, Warehouse $warehouse, string $currency, ?string $note)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->supplier = $supplier;
        $this->warehouse = $warehouse;
        $this->currency = $currency;
        $this->note = $note;
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function addItem(
        Product $product,
        string $quantity,
        string $unitCost,
        ?string $supplierSku,
        string $purchaseUnit = 'unit',
        string|int $stockUnitsPerPurchaseUnit = 1,
    ): void
    {
        $this->items->add(new PurchaseOrderItem(
            $this,
            $product,
            $quantity,
            $unitCost,
            $supplierSku,
            $purchaseUnit,
            $stockUnitsPerPurchaseUnit,
        ));
    }

    public function updateDraft(Supplier $supplier, Warehouse $warehouse, string $currency, ?string $note): void
    {
        if ($this->status !== 'draft') {
            throw new \DomainException('Only draft orders can be edited.');
        }
        $this->supplier = $supplier;
        $this->warehouse = $warehouse;
        $this->currency = $currency;
        $this->note = $note;
        $this->items->clear();
    }

    public function markSent(): void
    {
        $this->supplierSnapshot = [
            'name' => $this->supplier->getName(),
            'code' => $this->supplier->getCode(),
            'email' => $this->supplier->getEmail(),
            'phone' => $this->supplier->getPhone(),
            'contactName' => $this->supplier->getContactName(),
            'street' => $this->supplier->getStreet(),
            'postalCode' => $this->supplier->getPostalCode(),
            'city' => $this->supplier->getCity(),
            'country' => $this->supplier->getCountry(),
        ];
        $this->status = 'sent';
        $this->sentAt = new \DateTimeImmutable();
    }

    public function markReceived(): void
    {
        $remaining = false;
        foreach ($this->items as $item) {
            if ((float) $item->getOpenQuantity() > 0) {
                $remaining = true;
                break;
            }
        }
        $this->status = $remaining ? 'partially_received' : 'received';
    }

    public function cancel(): void
    {
        $this->status = 'cancelled';
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return 'PO-'.$this->createdAt->format('Ymd').'-'.strtoupper(str_replace('-', '', $this->id->toRfc4122()));
    }

    public function getTenant(): Tenant
    {
        return $this->tenant;
    }

    public function getSupplier(): Supplier
    {
        return $this->supplier;
    }

    public function getWarehouse(): Warehouse
    {
        return $this->warehouse;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getSupplierSnapshot(): ?array
    {
        return $this->supplierSnapshot;
    }

    public function recordEmail(string $recipient): void
    {
        $this->lastEmailedAt = new \DateTimeImmutable();
        $this->lastEmailedTo = $recipient;
    }

    public function getLastEmailedAt(): ?\DateTimeImmutable
    {
        return $this->lastEmailedAt;
    }

    public function getLastEmailedTo(): ?string
    {
        return $this->lastEmailedTo;
    }

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }
}
