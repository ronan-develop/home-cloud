<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Form\FileUploadType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\File;
use App\Service\FileUploader;
use App\Service\FileManager;
use App\Service\FileUploadValidator;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FileUploadController extends AbstractController
{
    private FileUploader $fileUploader;
    private FileManager $fileManager;
    private FileUploadValidator $fileUploadValidator;

    public function __construct(FileUploader $fileUploader, FileManager $fileManager, FileUploadValidator $fileUploadValidator)
    {
        $this->fileUploader = $fileUploader;
        $this->fileManager = $fileManager;
        $this->fileUploadValidator = $fileUploadValidator;
    }

    #[Route('/files/upload', name: 'file_upload', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): Response
    {
        $form = $this->createForm(FileUploadType::class);

        return $this->render('file/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/files/upload', name: 'file_upload', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function upload(Request $request): Response
    {
        $form = $this->createForm(FileUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('file')->getData();
            if ($uploadedFile) {
                // Validation métier externalisée
                try {
                    $this->fileUploadValidator->validate($uploadedFile);
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('danger', $e->getMessage());
                    return $this->render('file/upload.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                // Délégation à FileUploader
                $result = $this->fileUploader->upload($uploadedFile);

                // Délégation à FileManager pour la persistance
                $this->fileManager->createAndSave($result);

                $this->addFlash('success', 'Fichier uploadé avec succès !');
                return new RedirectResponse($request->getUri());
            }
        }

        return $this->render('file/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
