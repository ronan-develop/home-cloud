<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Repository\FileRepository;
use App\Repository\FolderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Recherche XHR : retourne dossiers + fichiers correspondant à la query.
 * Authentification session (firewall "web") — pas de JWT nécessaire.
 */
#[IsGranted('ROLE_USER')]
final class SearchController extends AbstractController
{
    public function __construct(
        private readonly FolderRepository $folderRepository,
        private readonly FileRepository $fileRepository,
    ) {}

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim($request->query->get('q', ''));

        if ($q === '') {
            return $this->json(['items' => []]);
        }

        /** @var User $user */
        $user = $this->getUser();

        $folders = $this->folderRepository->searchByName($q, $user);
        $files   = $this->fileRepository->searchByName($q, $user);

        $items = [];

        foreach ($folders as $folder) {
            $items[] = [
                'id'       => $folder->getId()->toRfc4122(),
                'name'     => $folder->getName(),
                'isFolder' => true,
                'url'      => '/?folder=' . $folder->getId()->toRfc4122(),
            ];
        }

        foreach ($files as $file) {
            $items[] = [
                'id'        => $file->getId()->toRfc4122(),
                'name'      => $file->getOriginalName(),
                'isFolder'  => false,
                'mimeType'  => $file->getMimeType(),
                'size'      => $file->getSize(),
                'folderId'  => $file->getFolder()->getId()->toRfc4122(),
                'folderUrl' => '/?folder=' . $file->getFolder()->getId()->toRfc4122(),
            ];
        }

        return $this->json(['items' => $items]);
    }
}
