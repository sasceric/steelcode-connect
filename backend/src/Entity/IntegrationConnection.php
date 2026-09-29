<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'integration_connections')]
#[ORM\UniqueConstraint(name: 'uniq_integration_connection_name', columns: ['tenant_id', 'name'])]
class IntegrationConnection
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tenant $tenant;

    #[ORM\Column(length: 32)]
    private string $connectorKey;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'json')]
    private array $directions;

    #[ORM\Column(type: 'json')]
    private array $configuration = [];

    #[ORM\Column(length: 16)]
    private string $status = 'configuring';

    #[ORM\Column]
    private bool $enabled = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastTestedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastTestMessage = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @param list<string> $directions @param array<string, scalar|array|null> $configuration */
    public function __construct(Tenant $tenant, string $connectorKey, string $name, array $directions, array $configuration = [])
    {
        $this->id = Uuid::v7();
        $this->tenant = $tenant;
        $this->connectorKey = $connectorKey;
        $this->name = $name;
        $this->directions = $directions;
        $this->configuration = $configuration;
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
    public function getConnectorKey(): string
    {
        return $this->connectorKey;
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function getDirections(): array
    {
        return $this->directions;
    }
    public function getConfiguration(): array
    {
        return $this->configuration;
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    /** @param array<string, scalar|array|null> $configuration */
    public function updateConfiguration(array $configuration): void
    {
        $this->configuration = $configuration;
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function setTestResult(string $status, string $message): void
    {
        $this->status = $status;
        $this->lastTestMessage = $message;
        $this->lastTestedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }
    public function activate(string $message): void
    {
        $this->enabled = true;
        $this->setTestResult('active', $message);
    }
    public function setEnabled(bool $enabled, string $message): void
    {
        $this->enabled = $enabled;
        $this->status = $enabled ? 'active' : 'inactive';
        $this->lastTestMessage = $message;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
