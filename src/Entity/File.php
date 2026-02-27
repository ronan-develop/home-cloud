<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ORM\Table(name: 'files')]
class File
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    /** Taille en octets */
    #[ORM\Column]
    private int $size;

    /** Chemin relatif dans var/storage/ (ex: "2026/02/uuid.pdf") */
    #[ORM\Column(length: 1024)]
    private string $path;

    #[ORM\ManyToOne(targetEntity: Folder::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Folder $folder;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $originalName,
        string $mimeType,
        int $size,
        string $path,
        Folder $folder,
        User $owner,
    ) {
        $this->id = Uuid::v7();
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->path = $path;
        $this->folder = $folder;
        $this->owner = $owner;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSize(): int { return $this->size; }
    public function getPath(): string { return $this->path; }
    public function getFolder(): Folder { return $this->folder; }
    public function getOwner(): User { return $this->owner; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
