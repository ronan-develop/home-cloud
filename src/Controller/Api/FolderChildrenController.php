<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\FolderRepository;
use App\Repository\FileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Récupère les enfants d'un dossier (dossiers + fichiers) en JSON.
 * Route publique mais authentifiée — les utilisateurs ne voient que leurs propres dossiers.
 */
#[IsGranted('ROLE_USER')]
final class FolderChildrenController extends AbstractController
{
    public function __construct(
        private readonly FolderRepository $folderRepository,
        private readonly FileRepository $fileRepository,
    ) {}

    #[Route('/api/v1/folders/{id}/children', name: 'api_folder_children', methods: ['GET'])]
    public function __invoke(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $folderId = Uuid::fromString($id);
        } catch (\Exception) {
            return $this->json(['error' => 'Invalid folder ID'], Response::HTTP_BAD_REQUEST);
        }

        $folder = $this->folderRepository->find($folderId);

        // Vérification de l'existence et de l'ownership
        if ($folder === null || !$folder->getOwner()->getId()->equals($user->getId())) {
            return $this->json(['error' => 'Folder not found or access denied'], Response::HTTP_FORBIDDEN);
        }

        // Récupère les enfants (dossiers ET fichiers)
        $childFolders = $this->folderRepository->findBy(
            ['parent' => $folder, 'owner' => $user],
            ['name' => 'ASC']
        );

        $childFiles = $this->fileRepository->findBy(
            ['folder' => $folder, 'owner' => $user],
            ['createdAt' => 'DESC']
        );

        // Formate la réponse JSON
        $items = [];

        foreach ($childFolders as $childFolder) {
            $items[] = [
                'id' => $childFolder->getId()->toRfc4122(),
                'name' => $childFolder->getName(),
                'isFolder' => true,
                'mediaType' => $childFolder->getMediaType()?->value ?? 'general',
            ];
        }

        foreach ($childFiles as $file) {
            $items[] = [
                'id' => $file->getId()->toRfc4122(),
                'name' => $file->getOriginalName(),
                'isFolder' => false,
                'mimeType' => $file->getMimeType(),
                'size' => $file->getSize(),
                'createdAt' => $file->getCreatedAt()?->format('Y-m-d'),
                'isNeutralized' => $file->isNeutralized(),
            ];
        }

        return $this->json(['items' => $items]);
    }
}
