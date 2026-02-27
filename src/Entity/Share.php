<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShareRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Entité représentant un partage de ressource entre deux utilisateurs.
 *
 * Rôle : permettre au owner d'une ressource (File, Folder, Album) de donner
 * un accès en lecture ou en écriture à un invité (guest).
 *
 * Choix :
 * - resourceType (file|folder|album) + resourceId (UUID) : relation polymorphe
 *   sans FK Doctrine (évite la complexité d'une union de tables).
 * - permission (read|write) : read = voir/télécharger, write = lire + uploader.
 * - expiresAt nullable : accès permanent si null, révocation automatique si défini.
 * - Révocation manuelle via DELETE /api/v1/shares/{id}.
 */
#[ORM\Entity(repositoryClass: ShareRepository::class)]
#[ORM\Table(name: 'shares')]
class Share
{
    public const PERMISSION_READ = 'read';
    public const PERMISSION_WRITE = 'write';

    public const RESOURCE_FILE = 'file';
    public const RESOURCE_FOLDER = 'folder';
    public const RESOURCE_ALBUM = 'album';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $guest;

    /** file | folder | album */
    #[ORM\Column(type: 'string', length: 10)]
    private string $resourceType;

    #[ORM\Column(type: 'uuid')]
    private Uuid $resourceId;

    /** read | write */
    #[ORM\Column(type: 'string', length: 5)]
    private string $permission;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $owner,
        User $guest,
        string $resourceType,
        Uuid $resourceId,
        string $permission,
        ?\DateTimeImmutable $expiresAt = null,
    ) {
        $this->id = Uuid::v7();
        $this->owner = $owner;
        $this->guest = $guest;
        $this->resourceType = $resourceType;
        $this->resourceId = $resourceId;
        $this->permission = $permission;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getOwner(): User { return $this->owner; }
    public function getGuest(): User { return $this->guest; }
    public function getResourceType(): string { return $this->resourceType; }
    public function getResourceId(): Uuid { return $this->resourceId; }
    public function getPermission(): string { return $this->permission; }
    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setPermission(string $permission): void { $this->permission = $permission; }
    public function setExpiresAt(?\DateTimeImmutable $expiresAt): void { $this->expiresAt = $expiresAt; }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < new \DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return !$this->isExpired();
    }
}
