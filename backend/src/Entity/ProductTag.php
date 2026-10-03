<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'product_tags')]
#[ORM\UniqueConstraint(name: 'uniq_product_tag', columns: ['product_id', 'tag_id'])]
class ProductTag
{
    use CatalogueAssignmentSources;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tag $tag;
    public function __construct(Product $product, Tag $tag)
    {
        $this->id = Uuid::v7();
        $this->product = $product;
        $this->tag = $tag;
    }
    public function getTag(): Tag
    {
        return $this->tag;
    }
}
