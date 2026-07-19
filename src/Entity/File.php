<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FileRepository::class)]
#[ORM\Table(name: 'files')]
/**
 * Entité représentant les métadonnées d'un fichier uploadé.
 *
 * Rôle : stocker ce qu'on sait du fichier (nom, type, taille, chemin) sans
 * contenir le binaire, qui est géré par StorageService sur le disque.
 *
 * Choix :
 * - UUID v7 (ordonné chronologiquement) comme identifiant.
 * - `path` est un chemin relatif à var/storage/ pour rester indépendant
 *   de l'emplacement absolu du projet en production.
 * - L'entité est immuable après création : pas de setters (remplacer = DELETE + POST).
 * - ManyToOne vers Folder (non nullable) : tout fichier appartient à un dossier,
 *   le dossier "Uploads" étant créé à la demande si aucun n'est spécifié.
 */
class File
{
    public const VISIBILITY_PRIVATE = 'private';
    public const VISIBILITY_LINK_ALLOWED = 'link_allowed';

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


    /**
     * Indique si le fichier a été neutralisé au stockage (stocké en .bin)
     * car son extension est potentiellement dangereuse côté navigateur
     * (svg, html, js, sh, py…). Le contenu reste intact, seule l'extension
     * sur disque est masquée. Le download restitue le vrai nom via Content-Disposition.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $neutralized = false;

    /**
     * private par défaut : le serveur refuse de créer un lien public tant que
     * l'owner n'a pas explicitement basculé la ressource en link_allowed.
     */
    #[ORM\Column(type: 'string', length: 12, options: ['default' => self::VISIBILITY_PRIVATE])]
    private string $visibility = self::VISIBILITY_PRIVATE;

    #[ORM\ManyToOne(targetEntity: Folder::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Folder $folder;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    /**
     * Lot d'upload d'origine, si le fichier vient d'un envoi corrélé côté client
     * (batchId). Nullable : les flux sans lot (route web, import album, fixtures)
     * restent inchangés. Sert au routage immediate/deferred et à la détection de
     * fin de lot pour la notification.
     */
    #[ORM\ManyToOne(targetEntity: UploadBatch::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?UploadBatch $batch = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $originalName,
        string $mimeType,
        int $size,
        string $path,
        Folder $folder,
        User $owner,
        bool $neutralized = false,
    ) {
        if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'test') {
            // Génère un UUID v4 aléatoire garanti unique
            $this->id = Uuid::fromString(
                sprintf(
                    '%08x-%04x-%04x-%04x-%012x',
                    random_int(0, 0xffffffff),
                    random_int(0, 0xffff),
                    (random_int(0, 0x0fff) | 0x4000), // version 4
                    (random_int(0, 0x3fff) | 0x8000), // variant
                    random_int(0, 0xffffffffffff)
                )
            );
        } else {
            $this->id = Uuid::v7();
        }
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->path = $path;
        $this->folder = $folder;
        $this->owner = $owner;
        $this->neutralized = $neutralized;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }
    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): void
    {
        $this->originalName = $originalName;
    }
    public function getMimeType(): string
    {
        return $this->mimeType;
    }
    public function getSize(): int
    {
        return $this->size;
    }
    public function getPath(): string
    {
        return $this->path;
    }
    public function getFolder(): Folder
    {
        return $this->folder;
    }

    /**
     * Déplace le fichier vers un autre dossier.
     * Utilisé uniquement par FileProcessor::handlePatch() (PATCH /api/v1/files/{id}).
     */
    public function setFolder(Folder $folder): void
    {
        $this->folder = $folder;
    }
    public function getOwner(): User
    {
        return $this->owner;
    }

    public function getBatch(): ?UploadBatch
    {
        return $this->batch;
    }

    public function setBatch(?UploadBatch $batch): void
    {
        $this->batch = $batch;
    }
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function isNeutralized(): bool
    {
        return $this->neutralized;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): void
    {
        $this->visibility = $visibility;
    }

    /**
     * Indique si l'entité est un dossier (toujours false pour File)
     */
    public function isFolder(): bool
    {
        return false;
    }
}
