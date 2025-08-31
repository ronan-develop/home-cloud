<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\FileRepository;
use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new \ApiPlatform\Metadata\Get(),
        new \ApiPlatform\Metadata\GetCollection(),
        new \ApiPlatform\Metadata\Post(),
        new \ApiPlatform\Metadata\Put(),
        new \ApiPlatform\Metadata\Delete()
    ]
)]
#[ORM\Entity(repositoryClass: FileRepository::class)]
class File
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $filename = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $path = null;

    #[ORM\Column]
    #[Assert\NotBlank]
    private ?int $size = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $mimeType = null;

    #[ORM\Column]
    #[Assert\NotBlank]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'files')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?PrivateSpace $privateSpace = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

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

    public function getPrivateSpace(): ?PrivateSpace
    {
        return $this->privateSpace;
    }

    public function setPrivateSpace(?PrivateSpace $privateSpace): static
    {
        $this->privateSpace = $privateSpace;

        return $this;
    }
}
