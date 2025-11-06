<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Form\FileUploadType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FileUploadController extends AbstractController
{
    #[Route('/files/upload', name: 'file_upload', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(Request $request): Response
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
            $file = $form->get('file')->getData();
            if ($file) {
                $uploadDir = $this->getParameter('app.files_dir');
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }
                $filename = uniqid() . '_' . $file->getClientOriginalName();
                $file->move($uploadDir, $filename);
                // TODO: Enregistrer les métadonnées en base (nom, chemin, taille, type, user...)
                $this->addFlash('success', 'Fichier uploadé avec succès !');
                return new RedirectResponse($request->getUri());
            }
        }

        return $this->render('file/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
