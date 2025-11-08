<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PhotosController extends AbstractController
{
    #[Route('/photos', name: 'photos_home')]
    public function index(): Response
    {
        return $this->render('photos/empty.html.twig');
    }
}
