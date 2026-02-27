<?php

declare(strict_types=1);

namespace App\Handler;

use App\Entity\Media;
use App\Message\MediaProcessMessage;
use App\Repository\FileRepository;
use App\Repository\MediaRepository;
use App\Service\ExifService;
use App\Service\ThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler Messenger qui traite le message MediaProcessMessage.
 *
 * Rôle : créer l'entité Media en extrayant les EXIF et en générant le thumbnail
 * de manière asynchrone, après que le File a été uploadé.
 *
 * Choix :
 * - Asynchrone (transport doctrine) : ne bloque pas la réponse HTTP de l'upload.
 * - Idempotent : si un Media existe déjà pour ce File, le handler s'arrête sans erreur.
 * - Dégradation gracieuse : EXIF manquant ou GD absent → Media créé sans ces données.
 * - Types supportés : image/* → "photo", video/* → "video", sinon ignoré.
 */
#[AsMessageHandler]
final class MediaProcessHandler
{
    private const MEDIA_MIME_TYPES = [
        'image/' => 'photo',
        'video/' => 'video',
    ];

    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $em,
        private readonly ExifService $exifService,
        private readonly ThumbnailService $thumbnailService,
    ) {}

    public function __invoke(MediaProcessMessage $message): void
    {
        $file = $this->fileRepository->find($message->fileId);
        if ($file === null) {
            return;
        }

        $mediaType = $this->resolveMediaType($file->getMimeType());
        if ($mediaType === null) {
            return;
        }

        // Idempotence : Media déjà créé pour ce File
        if ($this->mediaRepository->findOneBy(['file' => $file]) !== null) {
            return;
        }

        $media = new Media($file, $mediaType);

        if ($mediaType === 'photo') {
            $exif = $this->exifService->extract($file->getPath());
            $media->setWidth($exif['width']);
            $media->setHeight($exif['height']);
            $media->setTakenAt($exif['takenAt']);
            $media->setCameraModel($exif['cameraModel']);
            $media->setGpsLat($exif['gpsLat']);
            $media->setGpsLon($exif['gpsLon']);

            $thumb = $this->thumbnailService->generate($file->getPath());
            $media->setThumbnailPath($thumb);
        }

        $this->em->persist($media);
        $this->em->flush();
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
