<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Album;
use App\Entity\User;
use App\Interface\AlbumImportServiceInterface;
use App\Interface\AlbumServiceInterface;
use App\Interface\CreateFileServiceInterface;
use App\Interface\MediaProcessorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Orchestre l'import de photos depuis le disque directement dans un album
 * (SRP : compose CreateFileService, MediaProcessor et AlbumService sans
 * dupliquer leur logique).
 *
 * Différence avec le flux d'upload normal (FileUploadController) : le
 * traitement média (EXIF, thumbnail) est fait en synchrone ici, car
 * l'utilisateur attend déjà la fin de l'upload — le Media doit exister
 * immédiatement pour pouvoir l'ajouter à l'album dans la même requête.
 */
final class AlbumImportService implements AlbumImportServiceInterface
{
    public function __construct(
        private readonly CreateFileServiceInterface $createFileService,
        private readonly MediaProcessorInterface $mediaProcessor,
        private readonly AlbumServiceInterface $albumService,
    ) {}

    public function import(Album $album, array $files, User $owner): void
    {
        if ($files === []) {
            return;
        }

        $mediaIds = [];
        foreach ($files as $uploadedFile) {
            $file = $this->createFileService->createFromUpload($uploadedFile, (string) $owner->getId());

            $media = $this->mediaProcessor->process($file);
            if ($media !== null) {
                $mediaIds[] = $media->getId()->toRfc4122();
            }
        }

        $this->albumService->addMedias($album, $mediaIds, $owner);
    }
}
