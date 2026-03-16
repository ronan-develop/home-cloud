<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\FolderRepository;
use App\Repository\UserRepository;
use App\Entity\Folder;
use App\Service\FolderTreeService;

class FolderBrowserController extends AbstractController
{
    /**
     * Construit récursivement l'arborescence d'un dossier
     */
    private function buildTree($folder): array
    {
        $children = [];
        foreach ($folder->getChildren() as $child) {
            $children[] = $this->buildTree($child);
        }
        return [
            'id' => $folder->getId()->toRfc4122(),
            'name' => $folder->getName(),
            'children' => $children,
        ];
    }

    #[Route('/web/folders', name: 'web_folders')]
    public function index(FolderRepository $folderRepository, UserRepository $userRepository, FolderTreeService $treeService, \App\Factory\FolderTreeFactory $treeFactory): Response
    {
        // Crée le dossier racine si absent (premier accès)
        $treeFactory->ensureDefaultTree();

        // On récupère uniquement les dossiers racines (parent null)
        $rootFolders = $folderRepository->findBy(['parent' => null]);
        $tree = [];
        foreach ($rootFolders as $folder) {
            $tree[] = $treeService->buildTree($folder);
        }
        return $this->render('components/folder_browser.html.twig', [
            'tree' => $tree,
        ]);
    }
}
