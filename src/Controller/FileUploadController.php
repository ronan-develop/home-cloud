<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Form\FileUploadType;
use App\Service\FileUploader;
use App\Service\FileManager;
use App\Service\FileUploadValidator;
use App\Service\UploadFeedbackManager;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FileUploadController extends AbstractController
{
    private FileUploader $fileUploader;
    private FileManager $fileManager;
    private FileUploadValidator $fileUploadValidator;
    private UploadFeedbackManager $feedbackManager;

    public function __construct(FileUploader $fileUploader, FileManager $fileManager, FileUploadValidator $fileUploadValidator, UploadFeedbackManager $feedbackManager)
    {
        $this->fileUploader = $fileUploader;
        $this->fileManager = $fileManager;
        $this->fileUploadValidator = $fileUploadValidator;
        $this->feedbackManager = $feedbackManager;
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
                    return $this->feedbackManager->error($form, $e->getMessage());
                }

                // Délégation à FileUploader
                $result = $this->fileUploader->upload($uploadedFile);

                // Vérification stricte du type User
                $user = $this->getUser();
                if (!$user instanceof \App\Entity\User) {
                    throw new \LogicException('L’utilisateur courant n’est pas une entité User.');
                }
                $this->fileManager->createAndSave($result, $user);

                return $this->feedbackManager->success($request, 'Fichier uploadé avec succès !');
            }
        }

        return $this->feedbackManager->error($form, '');
    }
}
