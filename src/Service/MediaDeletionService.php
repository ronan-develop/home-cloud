<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Media;
use App\Interface\MediaDeletionServiceInterface;
use App\Interface\StorageServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Supprime définitivement un média (SRP : orchestration disque + entité,
 * séparée de FileActionService qui ne gère que File — pas de notion de
 * thumbnail ni de cascade Media/AlbumMedia).
 *
 * Le File et les associations AlbumMedia sont retirés en cascade au niveau
 * DB (onDelete: CASCADE sur Media::$file et AlbumMedia::$media) au flush.
 */
final class MediaDeletionService implements MediaDeletionServiceInterface
{
    public function __construct(
        private readonly StorageServiceInterface $storageService,
        private readonly EntityManagerInterface $em,
    ) {}

    public function delete(Media $media): void
    {
        $this->deleteFromDiskGracefully($media->getFile()->getPath());

        if ($media->getThumbnailPath() !== null) {
            $this->deleteFromDiskGracefully($media->getThumbnailPath());
        }

        $this->em->remove($media);
        $this->em->flush();
    }

    private function deleteFromDiskGracefully(string $relativePath): void
    {
        try {
            $this->storageService->delete($relativePath);
        } catch (\Exception) {
            // Fichier déjà absent du disque : pas bloquant, l'entité doit
            // quand même être supprimée.
        }
    }
}
