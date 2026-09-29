<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'integration_import_logs')]
#[ORM\Index(
    name: 'idx_integration_import_log_run_created',
    columns: ['run_id', 'created_at'],
)]
class IntegrationImportLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private IntegrationImportRun $run;

    #[ORM\Column(length: 16)]
    private string $level;

    #[ORM\Column(length: 64)]
    private string $stage;

    #[ORM\Column(type: 'text')]
    private string $message;

    /** @var array<string, scalar|array|null> */
    #[ORM\Column(type: 'json')]
    private array $context;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @param array<string, scalar|array|null> $context */
    public function __construct(
        IntegrationImportRun $run,
        string $level,
        string $stage,
        string $message,
        array $context = [],
    ) {
        $this->id = Uuid::v7();
        $this->run = $run;
        $this->level = mb_substr($level, 0, 16);
        $this->stage = mb_substr($stage, 0, 64);
        $this->message = mb_substr($message, 0, 2000);
        $this->context = $context;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRun(): IntegrationImportRun
    {
        return $this->run;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getStage(): string
    {
        return $this->stage;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /** @return array<string, scalar|array|null> */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
