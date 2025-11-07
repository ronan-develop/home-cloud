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
}
