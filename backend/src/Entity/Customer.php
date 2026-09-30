<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'customers')]
#[ORM\UniqueConstraint(name: 'uniq_customer_connection_external', columns: ['connection_id', 'external_id'])]
#[ORM\Index(name: 'idx_customer_tenant_email', columns: ['tenant_id', 'email'])]
#[ORM\Index(name: 'idx_customer_tenant_number', columns: ['tenant_id', 'customer_number'])]
class Customer
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?IntegrationConnection $connection;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $externalId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $firstName;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $lastName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $company;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $phone;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customerNumber = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $accountType = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(nullable: true)]
    private ?bool $active = null;

    #[ORM\Column(type: 'json')]
    private array $vatIds = [];

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $defaultBillingAddressExternalId = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $defaultShippingAddressExternalId = null;

    #[ORM\Column]
    private bool $profileImported = false;

    #[ORM\Column]
    private bool $guest = false;

    #[ORM\Column(type: 'json')]
    private array $sourcePayload = [];

    /** @var Collection<int, CustomerAddress> */
    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: CustomerAddress::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $addresses;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        Tenant $tenant,
        ?IntegrationConnection $connection = null,
        ?string $externalId = null,
    ) {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->connection = $connection;
        $this->externalId = $externalId;
        $this->addresses = new ArrayCollection();
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

    public function getConnection(): ?IntegrationConnection
    {
        return $this->connection;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function isGuest(): bool
    {
        return $this->guest;
    }

    public function getCustomerNumber(): ?string
    {
        return $this->customerNumber;
    }

    public function getAccountType(): ?string
    {
        return $this->accountType;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    /** @return list<string> */
    public function getVatIds(): array
    {
        return $this->vatIds;
    }

    public function getDefaultBillingAddressExternalId(): ?string
    {
        return $this->defaultBillingAddressExternalId;
    }

    public function getDefaultShippingAddressExternalId(): ?string
    {
        return $this->defaultShippingAddressExternalId;
    }

    public function isProfileImported(): bool
    {
        return $this->profileImported;
    }

    /** @return array<string, mixed> */
    public function getSourcePayload(): array
    {
        return $this->sourcePayload;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, CustomerAddress> */
    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    /** @param array<string, mixed> $profile */
    public function updateProfile(array $profile): void
    {
        $this->email = $profile['email'] ?? null;
        $this->firstName = $profile['firstName'] ?? null;
        $this->lastName = $profile['lastName'] ?? null;
        $this->company = $profile['company'] ?? null;
        $this->phone = $profile['phone'] ?? null;
        $this->guest = (bool) ($profile['guest'] ?? false);
        $this->customerNumber = $profile['customerNumber'] ?? null;
        $this->accountType = $profile['accountType'] ?? null;
        $this->title = $profile['title'] ?? null;
        $this->active = $profile['active'] ?? null;
        $this->vatIds = $profile['vatIds'] ?? [];
        $this->defaultBillingAddressExternalId = $profile['defaultBillingAddressId'] ?? null;
        $this->defaultShippingAddressExternalId = $profile['defaultShippingAddressId'] ?? null;
        $this->sourcePayload = $profile;
        $this->profileImported = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @param array<string, mixed> $snapshot */
    public function seedFromOrder(array $snapshot): void
    {
        if ($this->profileImported) {
            return;
        }

        $this->email = $snapshot['email'] ?? null;
        $this->firstName = $snapshot['firstName'] ?? null;
        $this->lastName = $snapshot['lastName'] ?? null;
        $this->company = $snapshot['company'] ?? null;
        $this->phone = $snapshot['phone'] ?? null;
        $this->guest = (bool) ($snapshot['guest'] ?? false);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function addAddress(CustomerAddress $address): void
    {
        $this->addresses->add($address);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function removeAddress(CustomerAddress $address): void
    {
        $this->addresses->removeElement($address);
        $this->updatedAt = new \DateTimeImmutable();
    }
}
