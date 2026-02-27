<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MediaRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'medias')]
/**
 * Entité représentant l'enrichissement média d'un fichier uploadé.
 *
 * Rôle : stocker les métadonnées riches extraites d'une image ou vidéo
 * (EXIF, dimensions, GPS, thumbnail) en complément d'un File.
 *
 * Choix :
 * - Relation OneToOne vers File (composition, pas héritage) : File reste générique
 *   et indépendant. Media est créé uniquement si le MIME est image/* ou video/*.
 * - Tous les champs sont nullable : l'extraction EXIF peut échouer partiellement
 *   (photo sans GPS, image sans EXIF, GD absent pour le thumbnail…).
 * - Immuable après création : re-traiter = DELETE Media + re-dispatch.
 * - thumbnailPath : chemin relatif dans var/storage/thumbs/{uuid}.jpg
 */
class Media
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private File $file;

    /** photo | video | unknown */
    #[ORM\Column(length: 20)]
    private string $mediaType;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    /** Date de prise de vue extraite des EXIF */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $takenAt = null;

    /** Latitude GPS (EXIF) */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $gpsLat = null;

    /** Longitude GPS (EXIF) */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $gpsLon = null;

    /** Modèle d'appareil photo (EXIF Make + Model) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cameraModel = null;

    /** Chemin relatif du thumbnail dans var/storage/ */
    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $thumbnailPath = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(File $file, string $mediaType)
    {
        $this->id = Uuid::v7();
        $this->file = $file;
        $this->mediaType = $mediaType;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid { return $this->id; }
    public function getFile(): File { return $this->file; }
    public function getMediaType(): string { return $this->mediaType; }
    public function getWidth(): ?int { return $this->width; }
    public function getHeight(): ?int { return $this->height; }
    public function getTakenAt(): ?\DateTimeImmutable { return $this->takenAt; }
    public function getGpsLat(): ?string { return $this->gpsLat; }
    public function getGpsLon(): ?string { return $this->gpsLon; }
    public function getCameraModel(): ?string { return $this->cameraModel; }
    public function getThumbnailPath(): ?string { return $this->thumbnailPath; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setWidth(?int $width): void { $this->width = $width; }
    public function setHeight(?int $height): void { $this->height = $height; }
    public function setTakenAt(?\DateTimeImmutable $takenAt): void { $this->takenAt = $takenAt; }
    public function setGpsLat(?string $gpsLat): void { $this->gpsLat = $gpsLat; }
    public function setGpsLon(?string $gpsLon): void { $this->gpsLon = $gpsLon; }
    public function setCameraModel(?string $cameraModel): void { $this->cameraModel = $cameraModel; }
    public function setThumbnailPath(?string $thumbnailPath): void { $this->thumbnailPath = $thumbnailPath; }
}
