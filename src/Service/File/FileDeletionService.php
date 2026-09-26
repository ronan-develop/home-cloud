<?php

declare(strict_types=1);

namespace App\Service\File;

use App\Entity\File;
use App\Entity\Share;
use App\Interface\File\FileDeletionServiceInterface;
use App\Interface\Media\MediaDeletionServiceInterface;
use App\Interface\Media\MediaDetachServiceInterface;
use App\Interface\Share\SharedResourceCleanerInterface;
use App\Interface\File\StorageServiceInterface;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Suppression de fichier depuis l'interface web (session auth).
 *
 * Extrait de FileWebController::delete() (#446, suite #440) : décision
 * détacher-et-conserver (albums) vs suppression complète, choix entre
 * MediaDeletionService et suppression storage directe, nettoyage des
 * partages liés.
 */
final class FileDeletionService implements FileDeletionServiceInterface
{
    public function __construct(
        private readonly StorageServiceInterface $storage,
        private readonly EntityManagerInterface $em,
        private readonly SharedResourceCleanerInterface $sharedResourceCleaner,
        private readonly MediaRepository $mediaRepository,
        private readonly MediaDetachServiceInterface $mediaDetachService,
        private readonly MediaDeletionServiceInterface $mediaDeletionService,
    ) {}

    public function deleteFile(File $file, bool $keepInAlbums): bool
    {
        $media = $this->mediaRepository->findByFile($file);

        if ($media !== null && $keepInAlbums) {
            $this->mediaDetachService->detachAndDeleteFile($media);

            return true;
        }

        if ($media !== null) {
            // Media::$file est désormais onDelete: SET NULL (#246, plus de
            // CASCADE) : la suppression complète doit retirer le Media
            // explicitement, sinon il devient orphelin (file_id NULL) sans
            // que l'utilisateur ait choisi de le conserver.
            $this->mediaDeletionService->delete($media);
        } else {
            $this->storage->delete($file->getPath());
        }

        $this->sharedResourceCleaner->deleteByResource(Share::RESOURCE_FILE, $file->getId());
        if ($media === null) {
            $this->em->remove($file);
        }
        $this->em->flush();

        return false;
    }
}
