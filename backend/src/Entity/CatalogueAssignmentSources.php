<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** A single visible assignment can be claimed by several independent sources. */
trait CatalogueAssignmentSources
{
    #[ORM\Column(type: 'json', options: ['default' => '["manual"]'])]
    private array $sourceKeys = ['manual'];

    public function getSourceKeys(): array
    {
        return $this->sourceKeys;
    }

    public function initializeSource(string $sourceKey): void
    {
        $this->sourceKeys = [$sourceKey];
    }

    public function claimSource(string $sourceKey): void
    {
        if (!in_array($sourceKey, $this->sourceKeys, true)) {
            $this->sourceKeys[] = $sourceKey;
        }
    }

    /** Returns true only when no manual or connector claim remains. */
    public function releaseSource(string $sourceKey): bool
    {
        $this->sourceKeys = array_values(array_diff($this->sourceKeys, [$sourceKey]));

        return $this->sourceKeys === [];
    }
}
