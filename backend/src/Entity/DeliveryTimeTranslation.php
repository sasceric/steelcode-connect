<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'delivery_time_translations')]
#[ORM\UniqueConstraint(name: 'uniq_delivery_time_translation_locale', columns: ['delivery_time_id', 'locale_id'])]
class DeliveryTimeTranslation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private DeliveryTime $deliveryTime;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Locale $locale;

    #[ORM\Column(length: 255)]
    private string $name;

    public function __construct(DeliveryTime $deliveryTime, Locale $locale, string $name)
    {
        $this->id = Uuid::v7();
        $this->deliveryTime = $deliveryTime;
        $this->locale = $locale;
        $this->name = $name;
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDeliveryTime(): DeliveryTime
    {
        return $this->deliveryTime;
    }

    public function update(string $name): void
    {
        $this->name = $name;
    }
}
