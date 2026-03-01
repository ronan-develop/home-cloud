<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FolderMediaType;
use App\Repository\FolderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FolderRepository::class)]
#[ORM\Table(name: 'folders')]
class Folder
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Folder $parent;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    private Collection $children;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column(type: 'string', enumType: FolderMediaType::class, options: ['default' => 'general'])]
    private FolderMediaType $mediaType = FolderMediaType::General;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $name, User $owner, ?Folder $parent = null)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->owner = $owner;
        $this->parent = $parent;
        $this->children = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getParent(): ?Folder
    {
        return $this->parent;
    }

    public function setParent(?Folder $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function getMediaType(): FolderMediaType
    {
        return $this->mediaType;
    }

    public function setMediaType(FolderMediaType $mediaType): void
    {
        $this->mediaType = $mediaType;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
