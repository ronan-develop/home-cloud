<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\FileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ApiResource(
    normalizationContext: ['groups' => ['file:read']],
    denormalizationContext: ['groups' => ['file:write']]
)]
class File
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['file:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['file:read', 'file:write'])]
    private string $name;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['file:read', 'file:write'])]
    #[Assert\Regex(
        pattern: '/^(?!.*\.\.).*$/',
        message: 'Le chemin ne doit pas contenir de séquence ../ pour des raisons de sécurité.'
    )]
    private string $path;

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups(['file:read', 'file:write'])]
    private string $mimeType;

    #[ORM\Column(type: 'integer')]
    #[Groups(['file:read', 'file:write'])]
    #[Assert\Range(min: 1, max: self::MAX_FILE_SIZE, notInRangeMessage: 'La taille du fichier doit être comprise entre 1 octet et 100 Mo.')]
    private int $size;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['file:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['file:read'])]
    private User $owner;

    public const MAX_FILE_SIZE = 104857600; // 100 Mo

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }
}
