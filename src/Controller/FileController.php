<?php

namespace App\Controller;

use ZipArchive;
use App\Entity\File;
use App\Security\FilePathSecurity;
use App\Security\FileAccessManager;
use App\Security\FileMimeTypeGuesser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class FileController extends AbstractController
{

    #[Route('/files/download-zip', name: 'file_download_zip')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function downloadZip(EntityManagerInterface $em): BinaryFileResponse|RedirectResponse
    {
        $user = $this->getUser();
        $userId = ($user instanceof \App\Entity\User && method_exists($user, 'getId')) ? $user->getId() : 'unknown';
        $files = $em->getRepository(File::class)->findBy(['owner' => $user]);

        if (!$files || count($files) === 0) {
            $this->addFlash('warning', 'Aucun fichier à télécharger.');
            return $this->redirectToRoute('app_home');
        }
        $zipPath = sys_get_temp_dir() . '/homecloud_' . $userId . '_' . time() . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->addFlash('danger', 'Impossible de créer l’archive.');
            return $this->redirectToRoute('app_home');
        }
        foreach ($files as $file) {
            $zip->addFile($file->getPath(), $file->getName());
        }
        $zip->close();
        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'mes-fichiers-homecloud.zip'
        );
        $response->headers->set('Content-Type', 'application/zip');
        $response->deleteFileAfterSend(true);
        return $response;
    }
    #[Route('/files/bulk-delete', name: 'file_bulk_delete', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function bulkDelete(EntityManagerInterface $em, FileAccessManager $fileAccessManager, FilePathSecurity $filePathSecurity): RedirectResponse
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $ids = $request->request->all('files');
        $user = $this->getUser();
        $errorMsg = 'Accès refusé ou fichier inexistant.';
        $count = 0;
        if (!is_array($ids) || empty($ids)) {
            $this->addFlash('warning', 'Aucun fichier sélectionné.');
            return $this->redirectToRoute('app_home');
        }
        foreach ($ids as $id) {
            $file = $em->getRepository(File::class)->find($id);
            if (!$file) {
                continue;
            }
            try {
                $fileAccessManager->assertDeleteAccess($file, $user);
                $realPath = $filePathSecurity->assertSafePath($file->getPath());
                $filePathSecurity->deleteFile($realPath);
                $em->remove($file);
                $count++;
            } catch (\Throwable $e) {
                continue;
            }
        }
        $em->flush();
        $this->addFlash('success', $count . ' fichier(s) supprimé(s).');
        return $this->redirectToRoute('app_home');
    }

    #[Route('/files/download/{id}', name: 'file_download', requirements: ['id' => '\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function download(int $id, EntityManagerInterface $em, FileAccessManager $fileAccessManager, FilePathSecurity $filePathSecurity, FileMimeTypeGuesser $fileMimeTypeGuesser): BinaryFileResponse|RedirectResponse
    {
        $file = $em->getRepository(File::class)->find($id);
        // Message unique pour éviter fuite d'info
        $errorMsg = 'Accès refusé ou fichier inexistant.';
        if (!$file) {
            $this->addFlash('danger', $errorMsg);
            return $this->redirectToRoute('file_upload');
        }
        $fileAccessManager->assertDownloadAccess($file, $this->getUser());
        try {
            $realPath = $filePathSecurity->assertSafePath($file->getPath());
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $errorMsg);
            return $this->redirectToRoute('file_upload');
        }
        $response = new BinaryFileResponse($realPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $file->getName()
        );
        // Content-Type sécurisé via service
        $mime = $fileMimeTypeGuesser->getSafeMimeType($file);
        $response->headers->set('Content-Type', $mime);
        return $response;
    }

    #[Route('/files/delete/{id}', name: 'file_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(int $id, EntityManagerInterface $em, FileAccessManager $fileAccessManager, FilePathSecurity $filePathSecurity): RedirectResponse
    {
        $file = $em->getRepository(File::class)->find($id);
        $errorMsg = 'Accès refusé ou fichier inexistant.';
        if (!$file) {
            $this->addFlash('danger', $errorMsg);
            return $this->redirectToRoute('file_upload');
        }
        $fileAccessManager->assertDeleteAccess($file, $this->getUser());
        try {
            $realPath = $filePathSecurity->assertSafePath($file->getPath());
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $errorMsg);
            return $this->redirectToRoute('file_upload');
        }
        // Suppression physique sécurisée
        $filePathSecurity->deleteFile($realPath);
        $em->remove($file);
        $em->flush();
        $this->addFlash('success', 'Fichier supprimé avec succès.');
        return $this->redirectToRoute('file_upload');
    }

    #[Route('/files/download-selected-zip', name: 'file_download_selected_zip', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function downloadSelectedZip(EntityManagerInterface $em): BinaryFileResponse|RedirectResponse
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $ids = $request->request->all('files');
        $user = $this->getUser();
        $userId = ($user instanceof \App\Entity\User && method_exists($user, 'getId')) ? $user->getId() : 'unknown';
        if (!is_array($ids) || empty($ids)) {
            $this->addFlash('warning', 'Aucun fichier sélectionné.');
            return $this->redirectToRoute('app_home');
        }
        // On ne prend que les fichiers appartenant à l'utilisateur
        $files = $em->getRepository(File::class)->findBy(['id' => $ids, 'owner' => $user]);
        if (!$files || count($files) === 0) {
            $this->addFlash('warning', 'Aucun fichier à télécharger.');
            return $this->redirectToRoute('app_home');
        }
        $zipPath = sys_get_temp_dir() . '/homecloud_' . $userId . '_' . time() . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            $this->addFlash('danger', 'Impossible de créer l’archive.');
            return $this->redirectToRoute('app_home');
        }
        foreach ($files as $file) {
            $zip->addFile($file->getPath(), $file->getName());
        }
        $zip->close();
        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'mes-fichiers-homecloud.zip'
        );
        $response->headers->set('Content-Type', 'application/zip');
        $response->deleteFileAfterSend(true);
        return $response;
    }
}
