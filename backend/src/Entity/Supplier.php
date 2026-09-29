<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'suppliers')]
#[ORM\UniqueConstraint(name: 'uniq_supplier_tenant_code', columns: ['tenant_id', 'code'])]
class Supplier
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 64)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $country = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Tenant $tenant, string $code, string $name)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->code = $code;
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function updateDetails(
        ?string $contactName,
        ?string $street,
        ?string $postalCode,
        ?string $city,
        ?string $country,
    ): void {
        $this->contactName = $contactName;
        $this->street = $street;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->country = $country;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function update(
        string $name,
        string $code,
        ?string $email,
        ?string $phone,
        bool $active,
    ): void {
        $this->name = $name;
        $this->code = $code;
        $this->email = $email;
        $this->phone = $phone;
        $this->active = $active;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
