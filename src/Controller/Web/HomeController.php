<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Repository\FileRepository;
use App\Repository\FolderRepository;
use App\Repository\ShareRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Dashboard d'accueil (route /).
 * Affiche : salutation, cartes stats (stockage, fichiers/dossiers, partages), fichiers récents.
 */
#[IsGranted('ROLE_USER')]
final class HomeController extends AbstractController
{
    public function __construct(
        private readonly FolderRepository $folderRepository,
        private readonly FileRepository $fileRepository,
        private readonly ShareRepository $shareRepository,
    ) {}

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $folderCount = $this->folderRepository->countFiltered($user, null);
        $fileCount = $this->fileRepository->countByOwner($user);
        $activeSharesCount = $this->shareRepository->countActiveByOwner($user);

        $recentFolders = $this->folderRepository->findRecentByOwner($user, 5);
        $recentFiles = $this->fileRepository->findRecentByOwner($user, 5);

        // Fusionner et re-trier (folder + file) par createdAt DESC
        $recentItems = [];
        foreach ($recentFolders as $folder) {
            $recentItems[] = [
                'type' => 'folder',
                'id' => $folder->getId(),
                'name' => $folder->getName(),
                'createdAt' => $folder->getCreatedAt(),
            ];
        }
        foreach ($recentFiles as $file) {
            $recentItems[] = [
                'type' => 'file',
                'id' => $file->getId(),
                'name' => $file->getOriginalName(),
                'createdAt' => $file->getCreatedAt(),
            ];
        }

        // Trier par createdAt DESC et limiter à 5
        usort($recentItems, fn ($a, $b) => $b['createdAt'] <=> $a['createdAt']);
        $recentItems = array_slice($recentItems, 0, 5);

        return $this->render('web/dashboard.html.twig', [
            'folderCount' => $folderCount,
            'fileCount' => $fileCount,
            'totalCount' => $folderCount + $fileCount,
            'activeSharesCount' => $activeSharesCount,
            'recentItems' => $recentItems,
            'storageUsedLabel' => 'Calcul à implémenter',
        ]);
    }
}
