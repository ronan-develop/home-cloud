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
 * Media::$file est en onDelete: SET NULL (#246, plus de CASCADE) : File et
 * Media sont donc retirés explicitement ici, tous les deux. Un Media déjà
 * détaché (file null) est toléré — MediaBulkDeleteController peut alors
 * supprimer définitivement un média conservé sans File source.
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
        $file = $media->getFile();

        if ($file !== null) {
            $this->deleteFromDiskGracefully($file->getPath());
            $this->rawPreviewCache->evict($file->getPath());
        }

        if ($media->getThumbnailPath() !== null) {
            $this->deleteFromDiskGracefully($media->getThumbnailPath());
        }

        $this->em->remove($media);
        if ($file !== null) {
            $this->em->remove($file);
        }
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
