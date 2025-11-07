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
use App\Service\ErrorHandler;
use App\Service\UploadLogger;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FileUploadController extends AbstractController
{
    private FileUploader $fileUploader;
    private FileManager $fileManager;
    private FileUploadValidator $fileUploadValidator;
    private UploadFeedbackManager $feedbackManager;
    private ErrorHandler $errorHandler;
    private UploadLogger $uploadLogger;

    public function __construct(FileUploader $fileUploader, FileManager $fileManager, FileUploadValidator $fileUploadValidator, UploadFeedbackManager $feedbackManager, ErrorHandler $errorHandler, UploadLogger $uploadLogger)
    {
        $this->fileUploader = $fileUploader;
        $this->fileManager = $fileManager;
        $this->fileUploadValidator = $fileUploadValidator;
        $this->feedbackManager = $feedbackManager;
        $this->errorHandler = $errorHandler;
        $this->uploadLogger = $uploadLogger;
    }

    #[Route('/files/upload', name: 'file_upload', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): Response
    {
        $form = $this->createForm(FileUploadType::class);

        return $this->render('file/upload.html.twig', [
            'form' => $form->createView(),
            'largeFile' => false,
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
            $largeFile = false;
            if ($uploadedFile) {
                if ($uploadedFile->getSize() > 10 * 1024 * 1024) { // 10 Mo
                    $largeFile = true;
                }
                // Validation métier externalisée
                try {
                    $this->fileUploadValidator->validate($uploadedFile);
                    $user = $this->getUser();
                    if (!$user instanceof \App\Entity\User) {
                        throw new \LogicException('L’utilisateur courant n’est pas une entité User.');
                    }
                    $this->uploadLogger->logValidation($uploadedFile, $user, 'Validation réussie');
                    // Délégation à FileUploader
                    $result = $this->fileUploader->upload($uploadedFile);
                    $this->fileManager->createAndSave($result, $user);
                    $this->uploadLogger->logSuccess($uploadedFile, $user);
                    return $this->render('file/upload.html.twig', [
                        'form' => $form->createView(),
                        'largeFile' => $largeFile,
                        'success' => true,
                        'message' => 'Fichier uploadé avec succès !',
                    ]);
                } catch (\InvalidArgumentException $e) {
                    $user = $this->getUser();
                    if ($uploadedFile && $user instanceof \App\Entity\User) {
                        $this->uploadLogger->logValidation($uploadedFile, $user, $e->getMessage());
                    }
                    return $this->render('file/upload.html.twig', [
                        'form' => $form->createView(),
                        'largeFile' => $largeFile,
                        'error' => true,
                        'message' => $e->getMessage(),
                    ]);
                } catch (\Throwable $e) {
                    $user = $this->getUser();
                    if ($uploadedFile && $user instanceof \App\Entity\User) {
                        $this->uploadLogger->logError($uploadedFile, $user, $e->getMessage());
                    }
                    return $this->render('file/upload.html.twig', [
                        'form' => $form->createView(),
                        'largeFile' => $largeFile,
                        'error' => true,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }
        return $this->render('file/upload.html.twig', [
            'form' => $form->createView(),
            'largeFile' => false,
        ]);
    }
}
