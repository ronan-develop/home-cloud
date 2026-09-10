<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Repository\FileRepository;
use App\Repository\FolderRepository;
use App\Repository\UserRepository;
use App\Security\AdminVoter;
use App\Service\FileSizeFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Page de détail d'un user dans l'espace admin (#375) : statut, actions
 * (désactivation/reset password) et détail du stockage par dossier racine
 * (cumul récursif, sous-dossiers inclus). Réservée à l'admin whitelisté
 * (AdminVoter → BroadcastAdminChecker).
 */
#[IsGranted(AdminVoter::ADMIN)]
final class AdminUserShowWebController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly FileRepository $fileRepository,
        private readonly FolderRepository $folderRepository,
        private readonly FileSizeFormatter $fileSizeFormatter,
    ) {}

    #[Route('/admin/users/{id}', name: 'app_admin_user_show', methods: ['GET'])]
    public function __invoke(string $id): Response
    {
        $user = $this->userRepository->findOwnerById(Uuid::fromString($id));
        if ($user === null) {
            throw $this->createNotFoundException();
        }

        $breakdown = [];
        foreach ($this->folderRepository->findRootFolders($user) as $folder) {
            $folderIds = [...$this->folderRepository->findDescendantIds($folder), $folder->getId()->toRfc4122()];

            $breakdown[] = [
                'folder'    => $folder,
                'fileCount' => $this->fileRepository->countByFolderIds($folderIds),
                'size'      => $this->fileSizeFormatter->format($this->fileRepository->sumSizeByFolderIds($folderIds)),
            ];
        }

        return $this->render('admin/user_show.html.twig', [
            'targetUser'   => $user,
            'totalStorage' => $this->fileSizeFormatter->format($this->fileRepository->sumSizeByOwner($user)),
            'totalFiles'   => $this->fileRepository->countByOwner($user),
            'breakdown'    => $breakdown,
        ]);
    }
}
