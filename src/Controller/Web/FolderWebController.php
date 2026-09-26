<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Share;
use App\Entity\User;
use App\Interface\FolderZipArchiverInterface;
use App\Repository\FolderRepository;
use App\Security\ResourceAccessChecker;
use App\Service\DefaultFolderService;
use App\Service\FolderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Gère la suppression de dossiers via l'interface web (session auth).
 *
 * Deux modes :
 *  - delete_contents=1 : suppression récursive complète
 *  - delete_contents=0 : déplacement de tous les fichiers vers Uploads, puis suppression
 */
#[IsGranted('ROLE_USER')]
final class FolderWebController extends AbstractController
{
    public function __construct(
        private readonly FolderRepository $folderRepository,
        private readonly ResourceAccessChecker $resourceAccessChecker,
        private readonly FolderZipArchiverInterface $folderZipArchiver,
        private readonly FolderService $folderService,
    ) {}

    #[Route('/folders/{id}/download', name: 'app_folder_download', methods: ['GET'])]
    public function download(string $id): BinaryFileResponse
    {
        $folder = $this->folderRepository->find($id)
            ?? throw $this->createNotFoundException('Dossier introuvable.');

        /** @var User $user */
        $user = $this->getUser();
        if (!$this->resourceAccessChecker->canRead($user, Share::RESOURCE_FOLDER, $folder->getId(), $folder->getOwner())) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas télécharger ce dossier.');
        }

        $zipPath = $this->folderZipArchiver->archive($folder);

        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $folder->getName() . '.zip');
        $response->headers->set('Content-Type', 'application/zip');
        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route('/folders/{id}/delete', name: 'app_folder_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-folder', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $folder = $this->folderRepository->find(Uuid::fromString($id));

        if ($folder === null) {
            throw $this->createNotFoundException('Dossier introuvable.');
        }

        $folderName = $folder->getName();
        $deleteContents = (bool) $request->request->get('delete_contents', '1');

        $this->folderService->deleteFolder($folder, $deleteContents);

        $message = "Dossier « {$folderName} » supprimé.";
        if (!$deleteContents) {
            $message .= ' Tous les fichiers ont été déplacés vers "' . DefaultFolderService::DEFAULT_FOLDER_NAME . '".';
        }
        $this->addFlash('success', $message);

        $redirectFolderId = $request->request->get('redirect_folder_id');

        return $this->redirect($redirectFolderId ? '/explorer?folder=' . $redirectFolderId : '/explorer');
    }
}
