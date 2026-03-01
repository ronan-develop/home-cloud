<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Repository\FileRepository;
use App\Repository\FolderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Point d'entrée de l'interface web (dashboard principal).
 * Toutes les routes sont protégées par le firewall session.
 */
#[IsGranted('ROLE_USER')]
final class HomeController extends AbstractController
{
    public function __construct(
        private readonly FolderRepository $folderRepository,
        private readonly FileRepository $fileRepository,
    ) {}

    #[Route('/', name: 'app_home')]
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

        $files = $this->fileRepository->findBy(
            $currentFolder ? ['folder' => $currentFolder, 'owner' => $user] : ['owner' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('web/home.html.twig', [
            'currentFolder' => $currentFolder,
            'folders'       => $folders,
            'files'         => $files,
        ]);
    }
}
