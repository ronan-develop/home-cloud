<?php

namespace App\Controller;

use App\Form\UploadablePhotoType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class PhotosController extends AbstractController
{
    #[Route('/photos', name: 'app_photos')]
    public function index(): Response
    {
        
        return $this->render('photos/index.html.twig', [
            'controller_name' => 'PhotosController',
        ]);
    }
    #[Route('/photos/upload', name: 'app_photos_upload')]
    public function upload(): Response
    {
        $form = $this->createForm(UploadablePhotoType::class);

        return $this->render('photo/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
