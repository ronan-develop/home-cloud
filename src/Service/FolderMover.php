<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Folder;
use App\Entity\User;
use App\Interface\DefaultFolderServiceInterface;
use App\Repository\FolderRepository;
use Doctrine\ORM\EntityManagerInterface;

final class FolderMover
{
    public function __construct(
        private readonly FolderRepository $folderRepository,
        private readonly DefaultFolderServiceInterface $defaultFolderService,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Move all files from $folder and its descendants into the Uploads folder
     * for the given user. Returns the target (uploads) folder entity.
     */
    public function moveContentsToUploads(Folder $folder, User $user): Folder
    {
        $uploadsFolder = $this->defaultFolderService->resolve(null, null, $user);

        $descendantIds = $this->folderRepository->findDescendantIds($folder);
        $allFolderIds  = array_merge([$folder->getId()->toRfc4122()], $descendantIds);

        foreach ($allFolderIds as $folderId) {
            $f = $this->folderRepository->find($folderId);
            if ($f === null) {
                continue;
            }

            foreach ($f->getFiles() as $file) {
                $file->setFolder($uploadsFolder);
            }
        }

        // Single flush for performance and to persist the uploads creation
        $this->em->flush();

        // Refresh source folders to clear collections from memory
        foreach ($allFolderIds as $folderId) {
            $f = $this->folderRepository->find($folderId);
            if ($f !== null) {
                $this->em->refresh($f);
            }
        }

        return $uploadsFolder;
    }
}
