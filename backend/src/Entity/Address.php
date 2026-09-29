<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'addresses')]
class Address
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Tenant $tenant;

    #[ORM\Column(length: 64)]
    private string $ownerType = 'tenant';

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $ownerId;

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column(length: 2)]
    private string $country;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $department = null;

    #[ORM\Column(length: 255)]
    private string $street;

    #[ORM\Column(length: 32)]
    private string $zipcode;

    #[ORM\Column(length: 255)]
    private string $city;

    #[ORM\Column(name: 'country_state', length: 255, nullable: true)]
    private ?string $countryState = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'additional_address_line1', length: 255, nullable: true)]
    private ?string $additionalAddressLine1 = null;

    #[ORM\Column(name: 'additional_address_line2', length: 255, nullable: true)]
    private ?string $additionalAddressLine2 = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @param array<string, mixed> $data */
    public function __construct(Tenant $tenant, array $data)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->ownerId = $tenant->getId();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->update($data);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }
    public function isDefault(): bool
    {
        return $this->isDefault;
    }
    public function setDefault(bool $default): void
    {
        $this->isDefault = $default;
    }
    public function getCountry(): string
    {
        return $this->country;
    }
    public function getCompany(): ?string
    {
        return $this->company;
    }
    public function getDepartment(): ?string
    {
        return $this->department;
    }
    public function getStreet(): string
    {
        return $this->street;
    }
    public function getZipcode(): string
    {
        return $this->zipcode;
    }
    public function getCity(): string
    {
        return $this->city;
    }
    public function getCountryState(): ?string
    {
        return $this->countryState;
    }
    public function getPhone(): ?string
    {
        return $this->phone;
    }
    public function getAdditionalAddressLine1(): ?string
    {
        return $this->additionalAddressLine1;
    }
    public function getAdditionalAddressLine2(): ?string
    {
        return $this->additionalAddressLine2;
    }

    /** @param array<string, mixed> $data */
    public function update(array $data): void
    {
        $this->country = strtoupper((string) $data['country']);
        $this->company = $data['company'] ?? null;
        $this->department = $data['department'] ?? null;
        $this->street = (string) $data['street'];
        $this->zipcode = (string) $data['zipcode'];
        $this->city = (string) $data['city'];
        $this->countryState = $data['countryState'] ?? null;
        $this->phone = $data['phone'] ?? null;
        $this->additionalAddressLine1 = $data['additionalAddressLine1'] ?? null;
        $this->additionalAddressLine2 = $data['additionalAddressLine2'] ?? null;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
