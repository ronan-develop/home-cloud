<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Media;
use App\Entity\Share;
use App\Interface\MediaDetachServiceInterface;
use App\Interface\SharedResourceCleanerInterface;
use App\Interface\StorageServiceInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Supprime le File source d'un Media en conservant le Media lui-même
 * (SRP : orchestration disque + entité File, séparée de MediaDeletionService
 * qui supprime tout — Media compris — et de FileActionService qui ne gère
 * que File sans notion de Media/album).
 */
final class MediaDetachService implements MediaDetachServiceInterface
{
    public function __construct(
        private readonly StorageServiceInterface $storageService,
        private readonly SharedResourceCleanerInterface $sharedResourceCleaner,
        private readonly EntityManagerInterface $em,
    ) {}

    public function detachAndDeleteFile(Media $media): void
    {
        $file = $media->getFile();
        if ($file === null) {
            throw new \LogicException('Media is already detached from its File.');
        }

        try {
            $this->storageService->delete($file->getPath());
        } catch (\Exception) {
            // Fichier déjà absent du disque : pas bloquant, le détachement
            // doit quand même s'appliquer.
        }

        $this->sharedResourceCleaner->deleteByResource(Share::RESOURCE_FILE, $file->getId());

        // Ordre impératif : detach() avant remove($file), pour que
        // l'UnitOfWork voie medias.file_id passer à NULL avant la
        // suppression de la ligne files (onDelete: SET NULL côté DB).
        $media->detach();
        $this->em->remove($file);
        $this->em->flush();
    }
}
