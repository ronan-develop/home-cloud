<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Interface\AlbumRepositoryInterface;
use App\Interface\FileRepositoryInterface;
use App\Interface\FolderRepositoryInterface;
use App\Interface\ResourceLocatorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Résout une ressource polymorphe (resourceType + resourceId) vers son entité réelle.
 *
 * Point d'entrée unique pour traduire la paire (type, id) stockée sur `Share`
 * en File|Folder|Album, sans dupliquer un `match` dans chaque appelant.
 */
final readonly class ResourceLocator implements ResourceLocatorInterface
{
    public function __construct(
        private FileRepositoryInterface $fileRepository,
        private FolderRepositoryInterface $folderRepository,
        private AlbumRepositoryInterface $albumRepository,
    ) {}

    public function locate(string $resourceType, Uuid $resourceId): File|Folder|Album
    {
        $resource = match ($resourceType) {
            Share::RESOURCE_FILE   => $this->fileRepository->find($resourceId),
            Share::RESOURCE_FOLDER => $this->folderRepository->find($resourceId),
            Share::RESOURCE_ALBUM  => $this->albumRepository->findById($resourceId),
            default => throw new NotFoundHttpException('Type de ressource inconnu.'),
        };

        return $resource ?? throw new NotFoundHttpException('Ressource introuvable.');
    }
}
