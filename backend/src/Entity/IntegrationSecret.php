<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'integration_secrets')]
#[ORM\UniqueConstraint(name: 'uniq_integration_secret_key', columns: ['connection_id', 'secret_key'])]
class IntegrationSecret
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private IntegrationConnection $connection;

    #[ORM\Column(length: 64)]
    private string $secretKey;

    #[ORM\Column(type: 'text')]
    private string $ciphertext;

    #[ORM\Column(length: 64)]
    private string $nonce;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(IntegrationConnection $connection, string $secretKey, string $ciphertext, string $nonce)
    {
        $this->id = Uuid::v7();
        $this->connection = $connection;
        $this->secretKey = $secretKey;
        $this->ciphertext = $ciphertext;
        $this->nonce = $nonce;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }
    public function getCiphertext(): string
    {
        return $this->ciphertext;
    }
    public function getNonce(): string
    {
        return $this->nonce;
    }
}
