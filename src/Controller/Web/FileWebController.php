<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\File;
use App\Entity\Share;
use App\Interface\MediaDeletionServiceInterface;
use App\Interface\MediaDetachServiceInterface;
use App\Interface\MediaProcessorInterface;
use App\Interface\StorageServiceInterface;
use App\Repository\FileRepository;
use App\Repository\MediaRepository;
use App\Interface\DefaultFolderServiceInterface;
use App\Interface\SharedResourceCleanerInterface;
use App\Security\GuestRestrictionChecker;
use App\Service\PdfSignatureDetector;
use App\Service\PendingMediaProcessingCollector;
use Doctrine\ORM\EntityManagerInterface;
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
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
        'exe', 'msi', 'com', 'bat', 'cmd', 'ps1', 'psm1', 'psd1', 'scr', 'pif',
        'vbs', 'vbe', 'wsf', 'wsh', 'gadget', 'msc', 'msp', 'mst',
        'run', 'elf', 'appimage', 'deb', 'rpm',
        'dmg', 'pkg', 'app',
        'jar', 'jnlp',
        'asp', 'aspx', 'jsp', 'cfm',
    ];

    public function __construct(
        private readonly StorageServiceInterface $storage,
        private readonly DefaultFolderServiceInterface $folderService,
        private readonly FileRepository $fileRepository,
        private readonly EntityManagerInterface $em,
        private readonly SharedResourceCleanerInterface $sharedResourceCleaner,
        private readonly GuestRestrictionChecker $guestRestrictionChecker,
        private readonly PendingMediaProcessingCollector $pendingMediaProcessingCollector,
        private readonly MediaProcessorInterface $mediaProcessor,
        private readonly PdfSignatureDetector $pdfSignatureDetector,
        private readonly MediaRepository $mediaRepository,
        private readonly MediaDetachServiceInterface $mediaDetachService,
        private readonly MediaDeletionServiceInterface $mediaDeletionService,
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

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$file->getOwner()->getId()->equals($user->getId())) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

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

        $ext = strtolower($uploadedFile->getClientOriginalExtension() ?? '');
        if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            throw new BadRequestHttpException(
                sprintf('File type ".%s" is not allowed.', $ext)
            );
        }

        $folder = $this->folderService->resolve($folderId, null, $user);

        // Retire les caractères de contrôle ET < > (neutralise le XSS stocké si ce nom
        // est un jour affiché sans échappement côté client — défense en profondeur, cf.
        // FilenameValidator qui rejette ces mêmes caractères sur les autres chemins).
        $originalName = preg_replace('/[\x00-\x1F\x7F<>]/u', '', $uploadedFile->getClientOriginalName());
        $mimeType = $uploadedFile->getClientMimeType();
        $size = $uploadedFile->getSize();  // Avant store() qui déplace le fichier

        ['path' => $path, 'neutralized' => $neutralized] = $this->storage->store($uploadedFile);

        $file = new File(
            $originalName,
            $mimeType,
            $size,
            $path,
            $folder,
            $user,
            $neutralized,
        );

        $this->em->persist($file);
        $this->em->flush();

        // Route web (un seul fichier par requête, pas de notion de lot) : le
        // traitement média se fait toujours juste après la réponse HTTP
        // (kernel.terminate, cf. ProcessPendingMediaListener), jamais via le
        // worker — celui-ci est réservé aux lots lourds déclarés par l'API.
        // supports() couvre aussi les RAW en application/octet-stream (reconnus
        // par extension).
        if ($this->mediaProcessor->supports($mimeType, $originalName)) {
            $this->pendingMediaProcessingCollector->add((string) $file->getId());
        }

        $this->addFlash('success', "Fichier « {$originalName} » uploadé avec succès.");

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

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$file->getOwner()->getId()->equals($user->getId())) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer ce fichier.');
        }

        $folderId = $request->request->get('folder_id');
        $keepInAlbums = (bool) $request->request->get('keep_in_albums', '0');
        $media = $this->mediaRepository->findByFile($file);

        if ($media !== null && $keepInAlbums) {
            try {
                $this->mediaDetachService->detachAndDeleteFile($media);
            } catch (\Throwable) {
                $this->addFlash('error', "Erreur lors de la suppression du fichier « {$file->getOriginalName()} ».");

                return $this->redirect($folderId ? '/explorer?folder=' . $folderId : '/explorer');
            }

            $this->addFlash('success', "Fichier « {$file->getOriginalName()} » supprimé, conservé dans vos albums.");

            return $this->redirect($folderId ? '/explorer?folder=' . $folderId : '/explorer');
        }

        try {
            if ($media !== null) {
                // Media::$file est désormais onDelete: SET NULL (#246, plus de
                // CASCADE) : la suppression complète doit retirer le Media
                // explicitement, sinon il devient orphelin (file_id NULL) sans
                // que l'utilisateur ait choisi de le conserver.
                $this->mediaDeletionService->delete($media);
            } else {
                $this->storage->delete($file->getPath());
            }
        } catch (\Throwable) {
            $this->addFlash('error', "Erreur lors de la suppression du fichier « {$file->getOriginalName()} ».");

            return $this->redirect($folderId ? '/explorer?folder=' . $folderId : '/explorer');
        }

        $this->sharedResourceCleaner->deleteByResource(Share::RESOURCE_FILE, $file->getId());
        if ($media === null) {
            $this->em->remove($file);
        }
        $this->em->flush();

        $this->addFlash('success', "Fichier « {$file->getOriginalName()} » supprimé.");

        return $this->redirect($folderId ? '/explorer?folder=' . $folderId : '/explorer');
    }
}
