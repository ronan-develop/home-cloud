<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Repository\FileRepository;
use App\Repository\FolderRepository;
use App\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Explorateur de fichiers.
 * Toutes les routes sont protégées par le firewall session.
 */
#[IsGranted('ROLE_USER')]
final class ExplorerController extends AbstractController
{
    public function __construct(
        private readonly FolderRepository $folderRepository,
        private readonly FileRepository $fileRepository,
        private readonly MediaRepository $mediaRepository,
    ) {}

    #[Route('/explorer', name: 'app_explorer')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $folderId = $request->query->get('folder');
        $currentFolder = null;

        if ($folderId !== null) {
            $currentFolder = $this->folderRepository->find(Uuid::fromString($folderId));
            // Vérification ownership
            if ($currentFolder !== null && !$currentFolder->getOwner()->getId()->equals($user->getId())) {
                $currentFolder = null;
                $folderId = null;
            }
        }

        $folders = $this->folderRepository->findBy(
            ['parent' => $currentFolder, 'owner' => $user],
            ['name' => 'ASC']
        );

        $files = $currentFolder
            ? $this->fileRepository->findBy(['folder' => $currentFolder, 'owner' => $user], ['createdAt' => 'DESC'])
            : [];

        // Vignettes : les Media du dossier en une requête, indexés par id de File.
        // La relation Media→File est unidirectionnelle — sans cette map, Twig n'a
        // aucun chemin vers la vignette. Une relation inverse lazy provoquerait un
        // N+1 sur chaque carte de la grille.
        $mediasByFileId = [];
        foreach ($this->mediaRepository->findBy(['file' => $files]) as $media) {
            $mediasByFileId[$media->getFile()->getId()->toRfc4122()] = $media;
        }

        // Construit le chemin complet (ancêtres) pour la breadcrumb
        $breadcrumbFolders = [];
        $ancestor = $currentFolder;
        while ($ancestor !== null) {
            array_unshift($breadcrumbFolders, $ancestor);
            $ancestor = $ancestor->getParent();
        }

        // Segments breadcrumb : Accueil + ancêtres cliquables + dossier courant (non cliquable)
        $breadcrumbSegments = [['label' => 'Tous les fichiers', 'url' => '/explorer']];
        foreach ($breadcrumbFolders as $i => $f) {
            $isLast = $i === array_key_last($breadcrumbFolders);
            $breadcrumbSegments[] = [
                'label' => $f->getName(),
                'url'   => $isLast ? null : '/explorer?folder=' . $f->getId()->toRfc4122(),
            ];
        }

        return $this->render('web/explorer.html.twig', [
            'currentFolder'      => $currentFolder,
            'breadcrumbSegments' => $breadcrumbSegments,
            'folders'            => $folders,
            'files'              => $files,
            'mediasByFileId'     => $mediasByFileId,
            'folderCount'        => count($folders),
            'fileCount'          => count($files),
            'sidebarTree'        => $this->folderRepository->findAllAsTree($user, $currentFolder),
        ]);
    }
}
