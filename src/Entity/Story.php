<?php

namespace App\Entity;

use App\Repository\StoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StoryRepository::class)]
class Story
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToMany(targetEntity: Image::class, inversedBy: 'stories')]
    private Collection $relatedImages;

    public function __construct()
    {
        $this->relatedImages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, Image>
     */
    public function getRelatedImages(): Collection
    {
        return $this->relatedImages;
    }

    public function addRelatedImage(Image $relatedImage): static
    {
        if (!$this->relatedImages->contains($relatedImage)) {
            $this->relatedImages->add($relatedImage);
        }

        return $this;
    }

    public function removeRelatedImage(Image $relatedImage): static
    {
        $this->relatedImages->removeElement($relatedImage);

        return $this;
    }
}
