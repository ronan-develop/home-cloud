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
 */
final class MediaProcessor implements MediaProcessorInterface
{
    private const MEDIA_MIME_TYPES = [
        'image/' => 'photo',
        'video/' => 'video',
    ];

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $em,
        private readonly ExifService $exifService,
        private readonly ThumbnailService $thumbnailService,
        private readonly StorageServiceInterface $storageService,
    ) {}

    public function process(File $file): ?Media
    {
        $mediaType = $this->resolveMediaType($file->getMimeType());
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

    private function resolveMediaType(string $mimeType): ?string
    {
        foreach (self::MEDIA_MIME_TYPES as $prefix => $type) {
            if (str_starts_with($mimeType, $prefix)) {
                return $type;
            }
        }

        return null;
    }
}
