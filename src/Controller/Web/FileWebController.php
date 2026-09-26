<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Interface\File\FileDeletionServiceInterface;
use App\Interface\File\FileUploadServiceInterface;
use App\Interface\Media\MediaProcessorInterface;
use App\Interface\OwnershipCheckerInterface;
use App\Interface\File\StorageServiceInterface;
use App\Repository\FileRepository;
use App\Security\GuestRestrictionChecker;
use App\Service\Media\PdfSignatureDetector;
use App\Service\Media\PendingMediaProcessingCollector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gère l'upload et la suppression de fichiers via l'interface web (session auth).
 * Réutilise StorageService et DefaultFolderService de la couche API.
 */
#[IsGranted('ROLE_USER')]
final class FileWebController extends AbstractController
{
    public function __construct(
        private readonly StorageServiceInterface $storage,
        private readonly FileRepository $fileRepository,
        private readonly GuestRestrictionChecker $guestRestrictionChecker,
        private readonly PendingMediaProcessingCollector $pendingMediaProcessingCollector,
        private readonly MediaProcessorInterface $mediaProcessor,
        private readonly PdfSignatureDetector $pdfSignatureDetector,
        private readonly OwnershipCheckerInterface $ownershipChecker,
        private readonly FileUploadServiceInterface $fileUploadService,
        private readonly FileDeletionServiceInterface $fileDeletionService,
    ) {}

    #[Route('/files/{id}/download', name: 'app_file_download', methods: ['GET'])]
    public function download(string $id): Response
    {
        return $this->buildFileResponse($id, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    /**
     * Affiche le fichier dans le navigateur au lieu de forcer son
     * téléchargement — un PDF s'ouvre alors avec le lecteur natif du
     * navigateur (pagination, zoom, recherche texte), sans aucune librairie
     * JS côté client (#241).
     */
    #[Route('/files/{id}/view', name: 'app_file_view', methods: ['GET'])]
    public function view(string $id): Response
    {
        return $this->buildFileResponse($id, ResponseHeaderBag::DISPOSITION_INLINE);
    }

    private function buildFileResponse(string $id, string $disposition): Response
    {
        $file = $this->fileRepository->find(\Symfony\Component\Uid\Uuid::fromString($id));

        if ($file === null) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $this->ownershipChecker->denyUnlessOwner($file);

        $absolutePath = $this->storage->getAbsolutePath($file->getPath());
        $response = new BinaryFileResponse($absolutePath);
        $response->setContentDisposition($disposition, $file->getOriginalName());

        // BinaryFileResponse devine Content-Type depuis le contenu réel du
        // fichier (magic bytes), pas depuis son extension .bin sur disque :
        // un fichier neutralisé (HTML/SVG dangereux) reçoit sinon son vrai
        // Content-Type (ex. text/html) — en inline, le navigateur le rend et
        // exécute le JS embarqué, contournant la neutralisation (#278).
        if ($file->isNeutralized()) {
            $response->headers->set('Content-Type', 'application/octet-stream');
        } elseif (
            str_ends_with(strtolower($file->getOriginalName()), '.pdf')
            && $response->headers->get('Content-Type') !== 'application/pdf'
            && $this->pdfSignatureDetector->detect($absolutePath)
        ) {
            // finfo (via BinaryFileResponse) peut détecter à tort
            // application/octet-stream sur un PDF valide mais dont l'en-tête
            // est décalé (ex: texte de debug fuité en préfixe par un site
            // tiers) — pourtant lisible par tout vrai lecteur PDF, cf.
            // PdfSignatureDetector.
            $response->headers->set('Content-Type', 'application/pdf');
        }

        return $response;
    }

    #[Route('/files/upload', name: 'app_file_upload', methods: ['POST'])]
    public function upload(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('file-upload', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $this->guestRestrictionChecker->denyUnlessFullAccount($user);

        $folderId = $request->request->get('folder_id');
        $uploadedFile = $request->files->get('file');

        if ($uploadedFile === null) {
            throw new BadRequestHttpException('No file provided.');
        }

        if ($uploadedFile->getError() !== \UPLOAD_ERR_OK) {
            $this->addFlash('error', 'Erreur d\'upload : ' . $uploadedFile->getErrorMessage());
            return $this->redirect($folderId ? '/explorer?folder=' . $folderId : '/explorer');
        }

        $file = $this->fileUploadService->createFromUpload($uploadedFile, $user, $folderId);

        // Route web (un seul fichier par requête, pas de notion de lot) : le
        // traitement média se fait toujours juste après la réponse HTTP
        // (kernel.terminate, cf. ProcessPendingMediaListener), jamais via le
        // worker — celui-ci est réservé aux lots lourds déclarés par l'API.
        // supports() couvre aussi les RAW en application/octet-stream (reconnus
        // par extension).
        if ($this->mediaProcessor->supports($file->getMimeType(), $file->getOriginalName())) {
            $this->pendingMediaProcessingCollector->add((string) $file->getId());
        }

        $this->addFlash('success', "Fichier « {$file->getOriginalName()} » uploadé avec succès.");

        $redirectUrl = '/explorer';
        if ($folderId) {
            $redirectUrl = '/explorer?folder=' . $folderId;
        }

        return $this->redirect($redirectUrl);
    }

    #[Route('/files/{id}/delete', name: 'app_file_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-file', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $file = $this->fileRepository->find(\Symfony\Component\Uid\Uuid::fromString($id));

        if ($file === null) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $this->ownershipChecker->denyUnlessOwner($file);

        $folderId = $request->request->get('folder_id');
        $keepInAlbums = (bool) $request->request->get('keep_in_albums', '0');

        try {
            $keptInAlbums = $this->fileDeletionService->deleteFile($file, $keepInAlbums);
        } catch (\Throwable) {
            $this->addFlash('error', "Erreur lors de la suppression du fichier « {$file->getOriginalName()} ».");

            return $this->redirect($folderId ? '/explorer?folder=' . $folderId : '/explorer');
        }

        $message = $keptInAlbums
            ? "Fichier « {$file->getOriginalName()} » supprimé, conservé dans vos albums."
            : "Fichier « {$file->getOriginalName()} » supprimé.";
        $this->addFlash('success', $message);

        return $this->redirect($folderId ? '/explorer?folder=' . $folderId : '/explorer');
    }
}
