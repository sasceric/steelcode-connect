<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'payment_methods')]
class PaymentMethod
{
    #[ORM\Id] #[ORM\Column(type: 'uuid')] private Uuid $id;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')] private Tenant $tenant;
    #[ORM\Column(length: 64)] private string $type;
    #[ORM\Column(length: 100)] private string $provider;
    #[ORM\Column(length: 255)] private string $label;
    #[ORM\Column(type: 'json')] private array $details = [];
    #[ORM\Column] private bool $active = true;
    #[ORM\Column] private bool $isDefault = false;
    public function __construct(Tenant $tenant, array $data)
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->update($data);
    }
    public function getId(): Uuid
    {
        return $this->id;
    } public function getType(): string
    {
        return $this->type;
    } public function getProvider(): string
    {
        return $this->provider;
    } public function getLabel(): string
    {
        return $this->label;
    } public function getDetails(): array
    {
        return $this->details;
    } public function isActive(): bool
    {
        return $this->active;
    } public function isDefault(): bool
    {
        return $this->isDefault;
    } public function setDefault(bool $v): void
    {
        $this->isDefault = $v;
    }
    public function update(array $data): void
    {
        $this->type = $data['type'];
        $this->provider = $data['provider'];
        $this->label = $data['label'];
        $this->details = $data['details'] ?? [];
        $this->active = (bool)($data['active'] ?? true);
    }
}
