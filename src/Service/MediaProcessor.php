<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\File;
use App\Entity\Media;
use App\Interface\MediaProcessorInterface;
use App\Interface\StorageServiceInterface;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;

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

            $exif = $this->exifService->extract($absolutePath);
            $media->setWidth($exif['width']);
            $media->setHeight($exif['height']);
            $media->setTakenAt($exif['takenAt']);
            $media->setCameraModel($exif['cameraModel']);
            $media->setGpsLat($exif['gpsLat']);
            $media->setGpsLon($exif['gpsLon']);

            $thumb = $this->thumbnailService->generate($absolutePath);
            $media->setThumbnailPath($thumb);
        }

        $this->em->persist($media);
        $this->em->flush();

        return $media;
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
        if ($this->isRawFile($originalName)) {
            return 'photo';
        }

        return null;
    }

    private function isRawFile(string $originalName): bool
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return in_array($extension, self::RAW_EXTENSIONS, true);
    }
}
