<?php

namespace App\Controller;

use App\Form\Handler\PhotoUploadHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class PhotoController extends AbstractController
{
    #[Route('/photos/upload', name: 'photo_upload')]
    public function upload(Request $request, UserInterface $user, PhotoUploadHandler $handler): Response
    {
        $result = $handler->handle($request, $user);
        if ($result->success) {
            $this->addFlash('success', 'Photo(s) uploadée(s) avec succès !');
            return $this->redirectToRoute('photos_home');
        }
        return $this->render('photos/upload.html.twig', [
            'form' => $result->form->createView(),
        ]);
    }
}
