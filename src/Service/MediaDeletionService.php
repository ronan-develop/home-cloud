<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Media;
use App\Interface\MediaDeletionServiceInterface;
use App\Interface\RawPreviewCacheInterface;
use App\Interface\StorageServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Supprime définitivement un média (SRP : orchestration disque + entité,
 * séparée de FileActionService qui ne gère que File — pas de notion de
 * thumbnail ni de cascade Media/AlbumMedia).
 *
 * La preview de RAW éventuellement mise en cache est évincée au passage : elle
 * pèse ~1 Mo et resterait sinon orpheline sur le disque.
 *
 * Le File et les associations AlbumMedia sont retirés en cascade au niveau
 * DB (onDelete: CASCADE sur Media::$file et AlbumMedia::$media) au flush.
 */
final class MediaDeletionService implements MediaDeletionServiceInterface
{
    public function __construct(
        private readonly StorageServiceInterface $storageService,
        private readonly EntityManagerInterface $em,
        private readonly RawPreviewCacheInterface $rawPreviewCache,
    ) {}

    public function delete(Media $media): void
    {
        $sourcePath = $media->getFile()->getPath();

        $this->deleteFromDiskGracefully($sourcePath);

        if ($media->getThumbnailPath() !== null) {
            $this->deleteFromDiskGracefully($media->getThumbnailPath());
        }

        // Sans effet pour un JPEG, qui n'a jamais de preview en cache.
        $this->rawPreviewCache->evict($sourcePath);

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
