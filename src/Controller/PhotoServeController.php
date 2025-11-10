<?php
// src/Controller/PhotoServeController.php

namespace App\Controller;

use App\Repository\PhotoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PhotoServeController extends AbstractController
{
    #[Route('/photo/view/{id}', name: 'photo_view')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function view(int $id, PhotoRepository $repo): BinaryFileResponse
    {
        $photo = $repo->find($id);
        $this->denyAccessUnlessGranted('view', $photo); // Voter ou logique d'accès

        $file = $this->getParameter('app.photos_dir') . '/' . $photo->getFilename();
        return (new BinaryFileResponse($file))
            ->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $photo->getOriginalName());
    }
}
