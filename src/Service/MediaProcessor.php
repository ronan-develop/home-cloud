<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\File;
use App\Entity\Media;
use App\Interface\MediaProcessorInterface;
use App\Interface\StorageServiceInterface;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use RonanLenouvel\RawPreviewExtractor\RawPreviewExtractorInterface;

/**
 * Crée l'entité Media d'un File en extrayant les EXIF et en générant le
 * thumbnail (SRP : logique métier pure, indépendante du canal d'appel).
 *
 * Appelé par MediaProcessHandler (async, transport Messenger) pour le flux
 * d'upload normal, et directement en synchrone par les flux qui ont besoin
 * du Media immédiatement après l'upload (ex: import direct dans un album).
 *
 * Choix :
 * - Idempotent : si un Media existe déjà pour ce File, le retourne tel quel.
 * - Dégradation gracieuse : EXIF manquant ou GD absent → Media créé sans ces données.
 * - Types supportés : image/* → "photo", video/* → "video", sinon retourne null.
 * - Fichiers RAW : les navigateurs n'ont pas de mimeType pour eux et envoient
 *   "application/octet-stream". On retombe alors sur l'extension, sans quoi aucun
 *   Media n'était créé et la vignette n'était jamais tentée.
 */
final class MediaProcessor implements MediaProcessorInterface
{
    private const MEDIA_MIME_TYPES = [
        'image/' => 'photo',
        'video/' => 'video',
    ];

    /**
     * Extensions RAW reconnues comme photos, quel que soit le mimeType déclaré.
     *
     * Volontairement alignée sur les formats que RawPreviewExtractor sait lire :
     * créer un Media pour un RAW dont on ne peut pas extraire de preview ne
     * produirait qu'une vignette vide.
     */
    private const RAW_EXTENSIONS = ['cr2', 'cr3', 'nef', 'arw', 'dng'];

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $em,
        private readonly ExifService $exifService,
        private readonly ThumbnailService $thumbnailService,
        private readonly StorageServiceInterface $storageService,
        private readonly RawPreviewExtractorInterface $rawPreviewExtractor,
        private readonly ExifValueFormatter $exifValueFormatter = new ExifValueFormatter(),
    ) {}

    public function process(File $file): ?Media
    {
        $mediaType = $this->resolveMediaType($file->getMimeType(), $file->getOriginalName());
        if ($mediaType === null) {
            return null;
        }

        $existing = $this->mediaRepository->findOneBy(['file' => $file]);
        if ($existing !== null) {
            return $existing;
        }

        $media = new Media($file, $mediaType);

        if ($mediaType === 'photo') {
            $absolutePath = $this->storageService->getAbsolutePath($file->getPath());

            // JPEG : EXIF natif ; RAW : exif_read_data ne sait pas ouvrir le
            // conteneur, on lit alors les métadonnées via le package (preview EXIF).
            if ($this->isRaw($file->getOriginalName())) {
                $this->applyRawMetadata($media, $absolutePath);
            } else {
                $this->applyExif($media, $this->exifService->extract($absolutePath));
            }

            // Vignette inchangée : toujours générée depuis le fichier d'origine
            // (ThumbnailService gère le RAW et son orientation).
            $thumb = $this->thumbnailService->generate($absolutePath);
            $media->setThumbnailPath($thumb);
        } elseif ($mediaType === 'video') {
            // Pas d'EXIF pour la vidéo (hors scope #312) : seulement la vignette.
            // ThumbnailService détecte lui-même la source, MediaProcessor ignore ffmpeg.
            $absolutePath = $this->storageService->getAbsolutePath($file->getPath());
            $media->setThumbnailPath($this->thumbnailService->generate($absolutePath));
        }

        $this->em->persist($media);
        $this->em->flush();

        return $media;
    }

    /**
     * Applique les EXIF d'un JPEG (tableau normalisé d'ExifService) au Media.
     *
     * @param array<string, mixed> $exif
     */
    private function applyExif(Media $media, array $exif): void
    {
        $media->setWidth($exif['width'] ?? null);
        $media->setHeight($exif['height'] ?? null);
        $media->setTakenAt($exif['takenAt'] ?? null);
        $media->setCameraModel($exif['cameraModel'] ?? null);
        $media->setGpsLat($exif['gpsLat'] ?? null);
        $media->setGpsLon($exif['gpsLon'] ?? null);
        $media->setAperture($exif['aperture'] ?? null);
        $media->setShutterSpeed($exif['shutterSpeed'] ?? null);
        $media->setIso($exif['iso'] ?? null);
        $media->setFocalLength($exif['focalLength'] ?? null);
        $media->setLens($exif['lens'] ?? null);
    }

    /**
     * Applique les métadonnées d'un RAW, lues via RawPreviewExtractor (EXIF de
     * la preview embarquée). Dégradation gracieuse : un RAW illisible ou sans
     * métadonnées laisse simplement les champs à null.
     */
    private function applyRawMetadata(Media $media, string $absolutePath): void
    {
        try {
            $meta = $this->rawPreviewExtractor->extract($absolutePath)->metadata;
        } catch (\Throwable) {
            return;
        }

        if ($meta === null) {
            return;
        }

        $media->setTakenAt($this->parseExifDate($meta->dateTimeOriginal));
        $media->setCameraModel($this->cameraName($meta->cameraMake, $meta->cameraModel));
        $media->setAperture($meta->fNumber !== null ? $this->exifValueFormatter->fNumber((string) $meta->fNumber) : null);
        $media->setShutterSpeed($meta->exposureTime);
        $media->setIso($meta->iso);
        $media->setFocalLength($meta->focalLength !== null ? $this->exifValueFormatter->focalLength((string) $meta->focalLength) : null);
        $media->setLens($meta->lensModel);
    }

    /**
     * Convertit une date EXIF "YYYY:MM:DD HH:MM:SS" en DateTimeImmutable, ou null.
     */
    private function parseExifDate(?string $raw): ?\DateTimeImmutable
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y:m:d H:i:s', $raw);

        return $date === false ? null : $date;
    }

    /**
     * Assemble "Make Model" en un libellé d'appareil, ou null si les deux manquent.
     */
    private function cameraName(?string $make, ?string $model): ?string
    {
        $name = trim(($make ?? '').' '.($model ?? ''));

        return $name === '' ? null : $name;
    }

    /**
     * Un fichier mérite-t-il un Media (photo/vidéo), sans toucher au disque ?
     *
     * Seule source de vérité pour cette décision — les contrôleurs d'upload
     * l'utilisent pour savoir s'il faut dispatcher/traiter, plutôt que de
     * dupliquer la logique image/video + extensions RAW à chaque appelant.
     */
    public function supports(string $mimeType, string $originalName): bool
    {
        return $this->resolveMediaType($mimeType, $originalName) !== null;
    }

    private function resolveMediaType(string $mimeType, string $originalName): ?string
    {
        foreach (self::MEDIA_MIME_TYPES as $prefix => $type) {
            if (str_starts_with($mimeType, $prefix)) {
                return $type;
            }
        }

        // Le mimeType ne dit rien d'utile (typiquement "application/octet-stream",
        // ce que les navigateurs envoient pour un RAW) : l'extension tranche.
        if ($this->isRaw($originalName)) {
            return 'photo';
        }

        return null;
    }

    /**
     * Le fichier est-il un RAW (reconnu par extension) ?
     *
     * Exposé publiquement pour être la seule source de vérité de cette décision,
     * réutilisée par UploadRoutingDecider (un RAW dans un lot force le déport au
     * worker, son décodage preview étant coûteux).
     */
    public function isRaw(string $originalName): bool
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return in_array($extension, self::RAW_EXTENSIONS, true);
    }
}
