<?php

namespace App\Controller;

use App\Form\Handler\PhotoUploadHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Service\PhotoFetcher;

class PhotoController extends AbstractController
{
    #[Route('/photos', name: 'photos_home')]
    public function index(PhotoFetcher $photoFetcher, UserInterface $user): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $photos = $photoFetcher->forUser($user);
        return $this->render('photos/index.html.twig', [
            'photos' => $photos,
        ]);
    }

    #[Route('/photos/upload', name: 'photo_upload')]
    public function upload(Request $request, UserInterface $user, PhotoUploadHandler $handler): Response
    {
        $result = $handler->handle($request, $user);
        if ($result->success) {
            $this->addFlash('success', 'Photo uploadée avec succès !');
            return $this->redirectToRoute('photos_home');
        }
        if ($result->errorMessage) {
            $this->addFlash('error', $result->errorMessage);
        }
        return $this->render('photos/upload.html.twig', [
            'form' => $result->form->createView(),
        ]);
    }
}
