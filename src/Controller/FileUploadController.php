<?php

namespace App\Controller;

use App\Form\FileUploadType;
use App\Service\FileUploadService;
use App\Service\FileUploadFormHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class FileUploadController extends AbstractController
{
    public function __construct(
        private FileUploadService $fileUploadService,
        private FileUploadFormHandler $fileUploadFormHandler
    ) {}

    #[Route('/files/upload', name: 'file_upload', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function upload(Request $request): Response
    {
        $form = $this->createForm(FileUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $uploadedFile = $this->fileUploadFormHandler->getUploadedFile($form);
                $this->fileUploadService->handle($uploadedFile, $this->getUser());
                $this->addFlash('success', 'Fichier uploadé avec succès !');
                return $this->redirectToRoute('file_upload');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
                return $this->redirectToRoute('file_upload');
            }
        }

        return $this->render('file/upload.html.twig', [
            'form' => $form,
            'largeFile' => false,
        ]);
    }
}
