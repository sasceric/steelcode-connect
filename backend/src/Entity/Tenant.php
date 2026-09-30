<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'tenants')]
class Tenant
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $oib = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $pdv = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $subscriptionPlan = null;

    #[ORM\Column(nullable: true)]
    private ?int $subscriptionPrice = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $subscriptionInterval = null;

    #[ORM\Column(length: 10, options: ['default' => 'en-GB'])]
    private string $defaultSnippetLocale = 'en-GB';

    #[ORM\Column(options: ['default' => 10000])]
    private int $nextProductNumber = 10000;

    /** @var list<string> */
    #[ORM\Column(type: 'json', options: ['default' => '["bs-BA", "de-DE", "en-GB"]'])]
    private array $enabledSnippetLocales = ['bs-BA', 'de-DE', 'en-GB'];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $subscriptionStartedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
    public function getOib(): ?string
    {
        return $this->oib;
    }
    public function setOib(?string $oib): void
    {
        $this->oib = $oib;
    }
    public function getPdv(): ?string
    {
        return $this->pdv;
    }
    public function setPdv(?string $pdv): void
    {
        $this->pdv = $pdv;
    }
    public function getPhone(): ?string
    {
        return $this->phone;
    }
    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }
    public function getEmail(): ?string
    {
        return $this->email;
    }
    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }
    public function getWebsite(): ?string
    {
        return $this->website;
    }
    public function setWebsite(?string $website): void
    {
        $this->website = $website;
    }
    public function getSubscriptionPlan(): ?string
    {
        return $this->subscriptionPlan;
    }
    public function getSubscriptionPrice(): ?int
    {
        return $this->subscriptionPrice;
    }
    public function getSubscriptionInterval(): ?string
    {
        return $this->subscriptionInterval;
    }
    public function getSubscriptionStartedAt(): ?\DateTimeImmutable
    {
        return $this->subscriptionStartedAt;
    }
    public function getDefaultSnippetLocale(): string
    {
        return $this->defaultSnippetLocale;
    }
    public function setDefaultSnippetLocale(string $locale): void
    {
        $this->defaultSnippetLocale = $locale;
    }

    public function getNextProductNumber(): int
    {
        return $this->nextProductNumber;
    }

    public function claimNextProductNumber(): int
    {
        $productNumber = $this->nextProductNumber;
        $this->nextProductNumber++;

        return $productNumber;
    }

    /** @return list<string> */
    public function getEnabledSnippetLocales(): array
    {
        return $this->enabledSnippetLocales;
    }

    /** @param list<string> $locales */
    public function setEnabledSnippetLocales(array $locales): void
    {
        $this->enabledSnippetLocales = array_values(array_unique($locales));
    }
    public function setSubscription(string $plan, int $price, string $interval): void
    {
        $this->subscriptionPlan = $plan;
        $this->subscriptionPrice = $price;
        $this->subscriptionInterval = $interval;
        $this->subscriptionStartedAt = new \DateTimeImmutable();
    }
}
