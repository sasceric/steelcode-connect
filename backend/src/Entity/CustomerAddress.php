<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'customer_addresses')]
#[ORM\UniqueConstraint(name: 'uniq_customer_address_external', columns: ['customer_id', 'external_id'])]
class CustomerAddress
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(inversedBy: 'addresses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $externalId;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $department = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $zipcode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $countryState = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $additionalAddressLine1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $additionalAddressLine2 = null;

    #[ORM\Column]
    private bool $billingDefault = false;

    #[ORM\Column]
    private bool $shippingDefault = false;

    #[ORM\Column(type: 'json')]
    private array $sourcePayload = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Customer $customer, ?string $externalId = null)
    {
        $this->id = Uuid::v7();
        $this->customer = $customer;
        $this->externalId = $externalId;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
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

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function getZipcode(): ?string
    {
        return $this->zipcode;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
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

    /** @return array<string, mixed> */
    public function getSourcePayload(): array
    {
        return $this->sourcePayload;
    }

    public function isBillingDefault(): bool
    {
        return $this->billingDefault;
    }

    public function isShippingDefault(): bool
    {
        return $this->shippingDefault;
    }

    public function setDefaultFlags(bool $billingDefault, bool $shippingDefault): void
    {
        $this->billingDefault = $billingDefault;
        $this->shippingDefault = $shippingDefault;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @param array<string, mixed> $address */
    public function update(
        array $address,
        bool $billingDefault,
        bool $shippingDefault,
    ): void {
        $this->firstName = $address['firstName'] ?? null;
        $this->lastName = $address['lastName'] ?? null;
        $this->company = $address['company'] ?? null;
        $this->department = $address['department'] ?? null;
        $this->title = $address['title'] ?? null;
        $this->street = $address['street'] ?? null;
        $this->zipcode = $address['zipcode'] ?? null;
        $this->city = $address['city'] ?? null;
        $this->countryCode = $address['countryCode'] ?? null;
        $this->countryState = $address['countryState'] ?? null;
        $this->phone = $address['phone'] ?? null;
        $this->additionalAddressLine1 = $address['additionalAddressLine1'] ?? null;
        $this->additionalAddressLine2 = $address['additionalAddressLine2'] ?? null;
        $this->billingDefault = $billingDefault;
        $this->shippingDefault = $shippingDefault;
        $this->sourcePayload = $address;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
