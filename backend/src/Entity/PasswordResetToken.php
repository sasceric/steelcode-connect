<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'password_reset_tokens')]
class PasswordResetToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;
    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;
    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(User $user, string $tokenHash)
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = new \DateTimeImmutable('+1 hour');
    }
    public function getUser(): User
    {
        return $this->user;
    }
    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }
    public function isUsable(): bool
    {
        return $this->usedAt === null && $this->expiresAt > new \DateTimeImmutable();
    }
    public function use(): void
    {
        $this->usedAt = new \DateTimeImmutable();
    }
}
