<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'locales')]
class Locale
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;
    #[ORM\Column(length: 10, unique: true)] private string $code;
    #[ORM\Column(length: 100)] private string $name;
    #[ORM\Column(length: 100)] private string $nativeName;
    #[ORM\Column(length: 3)] private string $direction = 'ltr';
    #[ORM\Column] private bool $active = true;
    public function __construct(string $code, string $name, string $nativeName)
    {
        $this->id = Uuid::v7();
        $this->code = $code;
        $this->name = $name;
        $this->nativeName = $nativeName;
    }
    public function getId(): Uuid
    {
        return $this->id;
    }
    public function getCode(): string
    {
        return $this->code;
    }
}
