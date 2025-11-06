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

class FileController extends AbstractController
{
    #[Route('/files/download/{id}', name: 'file_download', requirements: ['id' => '\\d+'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function download(int $id, EntityManagerInterface $em): BinaryFileResponse|RedirectResponse
    {
        $file = $em->getRepository(File::class)->find($id);
        if (!$file) {
            $this->addFlash('danger', 'Fichier introuvable.');
            return $this->redirectToRoute('file_upload');
        }
        // Protection d'accès par Voter
        $this->denyAccessUnlessGranted('FILE_DOWNLOAD', $file);

        $response = new BinaryFileResponse($file->getPath());
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $file->getName()
        );
        $response->headers->set('Content-Type', $file->getMimeType());
        return $response;
    }

    #[Route('/files/delete/{id}', name: 'file_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(int $id, EntityManagerInterface $em): RedirectResponse
    {
        $file = $em->getRepository(File::class)->find($id);
        if (!$file) {
            $this->addFlash('danger', 'Fichier introuvable.');
            return $this->redirectToRoute('file_upload');
        }
        // Protection d'accès par Voter
        $this->denyAccessUnlessGranted('FILE_DELETE', $file);

        // Suppression physique
        if (file_exists($file->getPath())) {
            @unlink($file->getPath());
        }
        $em->remove($file);
        $em->flush();
        $this->addFlash('success', 'Fichier supprimé avec succès.');
        return $this->redirectToRoute('file_upload');
    }
}
