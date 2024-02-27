<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UploadableFileController extends AbstractController
{
    #[Route('/uploadable/file', name: 'app_uploadable_file')]
    public function index(): Response
    {
        return $this->render('uploadable_file/index.html.twig', [
            'controller_name' => 'UploadableFileController',
        ]);
    }
}
