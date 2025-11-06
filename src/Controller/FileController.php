<?php

namespace App\Controller;

use App\Entity\File;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use App\Security\FileAccessManager;
use App\Security\FilePathSecurity;
use App\Security\FileMimeTypeGuesser;

class FileController extends AbstractController
{
    #[Route('/files/download/{id}', name: 'file_download', requirements: ['id' => '\\d+'])]
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
        try {
            $fileAccessManager->assertDownloadAccess($file, $this->getUser());
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('danger', $errorMsg);
            return $this->redirectToRoute('file_upload');
        }
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

    #[Route('/files/delete/{id}', name: 'file_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(int $id, EntityManagerInterface $em, FileAccessManager $fileAccessManager, FilePathSecurity $filePathSecurity): RedirectResponse
    {
        $file = $em->getRepository(File::class)->find($id);
        $errorMsg = 'Accès refusé ou fichier inexistant.';
        if (!$file) {
            $this->addFlash('danger', $errorMsg);
            return $this->redirectToRoute('file_upload');
        }
        try {
            $fileAccessManager->assertDeleteAccess($file, $this->getUser());
        } catch (\Symfony\Component\Security\Core\Exception\AccessDeniedException $e) {
            $this->addFlash('danger', $errorMsg);
            return $this->redirectToRoute('file_upload');
        }
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
}
