<?php

namespace App\Controller;

use App\Form\Handler\PhotoUploadHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoController extends AbstractController
{
    #[Route('/photos/upload', name: 'photo_upload')]
    public function upload(Request $request, UserInterface $user, PhotoUploadHandler $handler): Response
    {
        [$success, $form] = array_slice($handler->handle($request, $user), 0, 2);
        if ($success) {
            $this->addFlash('success', 'Photo uploadée avec succès !');
            return $this->redirectToRoute('photo_upload');
        }
        return $this->render('photos/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
