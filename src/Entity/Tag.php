<?php

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TagRepository::class)]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var Collection<int, Photo>
     */
    #[ORM\ManyToMany(targetEntity: Photo::class, inversedBy: 'tags')]
    private Collection $value;

    public function __construct()
    {
        $this->value = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return Collection<int, Photo>
     */
    public function getValue(): Collection
    {
        return $this->value;
    }

    public function addValue(Photo $value): static
    {
        if (!$this->value->contains($value)) {
            $this->value->add($value);
        }

        return $this;
    }

    public function removeValue(Photo $value): static
    {
        $this->value->removeElement($value);

        return $this;
    }
}
