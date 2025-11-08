<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\User;
use App\Service\FilePathService;
use App\Security\FilePathSecurity;
use App\Service\ZipArchiveService;
use App\Security\FileAccessManager;
use App\Security\FileMimeTypeGuesser;
use App\Service\FileSelectionService;
use App\Service\BulkFileDeleteService;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\FileErrorRedirectorService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\FileRepository;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class FileController extends AbstractController
{
    #[Route('/mes-fichiers', name: 'files_list')]
    public function listFiles(Request $request, FileRepository $fileRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        $page = max(1, (int) $request->query->get('page', 1));
        $query = $fileRepository->getFilesForUserQuery($user);
        $pagerfanta = new Pagerfanta(new QueryAdapter($query));
        $pagerfanta->setMaxPerPage(10);
        $pagerfanta->setCurrentPage($page);
        $lastFile = $fileRepository->getLastFileForUser($user);
        return $this->render('file/files.html.twig', [
            'filesPager' => $pagerfanta,
            'lastFile' => $lastFile,
        ]);
    }

    #[Route('/files/download-zip', name: 'file_download_zip')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function downloadZip(EntityManagerInterface $em, ZipArchiveService $zipArchiveService, FileErrorRedirectorService $errorRedirector): BinaryFileResponse|RedirectResponse
    {
        $user = $this->getUser();
        $userId = ($user instanceof User && method_exists($user, 'getId')) ? $user->getId() : 'unknown';
        $files = $em->getRepository(File::class)->findBy(['owner' => $user]);
        try {
            $response = $zipArchiveService->createZipResponse(
                $files,
                'mes-fichiers-homecloud.zip',
                (string)$userId
            );
            return $response;
        } catch (\RuntimeException $e) {
            return $errorRedirector->handle($e->getMessage(), 'file_upload');
        }
    }
    #[Route('/files/bulk-delete', name: 'file_bulk_delete', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function bulkDelete(BulkFileDeleteService $bulkFileDeleteService, FileErrorRedirectorService $errorRedirector): RedirectResponse
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $ids = $request->request->all('files');
        $user = $this->getUser();
        // Plus de if métier ici : tout est géré par exception dans le service
        $count = $bulkFileDeleteService->deleteFiles($ids, $user);
        $this->addFlash('success', $count . ' fichier(s) supprimé(s).');
        return $this->redirectToRoute('app_home');
    }

    #[Route('/files/download/{id}', name: 'file_download', requirements: ['id' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function download(
        int $id,
        EntityManagerInterface $em,
        FileAccessManager $fileAccessManager,
        FilePathSecurity $filePathSecurity,
        FileMimeTypeGuesser $fileMimeTypeGuesser,
        FilePathService $filePathService,
        FileErrorRedirectorService $errorRedirector
    ): BinaryFileResponse|RedirectResponse {
        /**
         * Toute la gestion d’erreur métier (fichier inexistant, accès refusé, chemin non valide, etc.)
         * est déportée dans les services via exception métier (fail-fast, SRP, testabilité).
         */
        $file = $em->getRepository(File::class)->find($id);
        $fileAccessManager->assertDownloadAccess($file, $this->getUser());
        $realPath = $filePathService->getSafePathOrNull($filePathSecurity, $file);
        $response = new BinaryFileResponse($realPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $file->getName()
        );
        $mime = $fileMimeTypeGuesser->getSafeMimeType($file);
        $response->headers->set('Content-Type', $mime);
        return $response;
    }

    #[Route('/files/delete/{id}', name: 'file_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(
        int $id,
        EntityManagerInterface $em,
        FileAccessManager $fileAccessManager,
        FilePathSecurity $filePathSecurity,
        FilePathService $filePathService,
        FileErrorRedirectorService $errorRedirector
    ): RedirectResponse {
        $file = $em->getRepository(File::class)->find($id);
        // Plus de if métier ici : tout est géré par exception dans le service
        $fileAccessManager->assertDeleteAccess($file, $this->getUser());
        $realPath = $filePathService->getSafePathOrNull($filePathSecurity, $file);
        // Suppression physique sécurisée
        $filePathSecurity->deleteFile($realPath);
        $em->remove($file);
        $em->flush();
        $this->addFlash('success', 'Fichier supprimé avec succès.');
        return $this->redirectToRoute('file_upload');
    }

    #[Route('/files/download-selected-zip', name: 'file_download_selected_zip', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function downloadSelectedZip(
        FileSelectionService $fileSelectionService,
        ZipArchiveService $zipArchiveService,
    ): BinaryFileResponse|RedirectResponse {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $ids = $request->request->all('files');
        $user = $this->getUser();
        $userId = ($user instanceof User && method_exists($user, 'getId')) ? $user->getId() : 'unknown';
        // Plus de if métier ici : tout est géré par exception dans le service
        $files = $fileSelectionService->getUserFilesByIds($ids, $user);
        return $zipArchiveService->createZipResponse($files, 'mes-fichiers-homecloud.zip', (string)$userId);
    }
}
