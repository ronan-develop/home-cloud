<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Form\FileUploadType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\File;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FileUploadController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }
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
            $uploadedFile = $form->get('file')->getData();
            if ($uploadedFile) {
                $uploadDir = $this->getParameter('app.files_dir');
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }
                $filename = uniqid() . '_' . $uploadedFile->getClientOriginalName();

                // Récupérer les métadonnées AVANT le move()
                $originalName = $uploadedFile->getClientOriginalName();
                $size = $uploadedFile->getSize();
                $mimeType = $uploadedFile->getClientMimeType();

                $uploadedFile->move($uploadDir, $filename);

                // Persistance des métadonnées en base
                $fileEntity = new File();
                $fileEntity->setName($originalName);
                $fileEntity->setPath($uploadDir . '/' . $filename);
                $fileEntity->setSize($size);
                $fileEntity->setMimeType($mimeType);
                $fileEntity->setUploadedAt(new \DateTimeImmutable());

                $this->em->persist($fileEntity);
                $this->em->flush();

                $this->addFlash('success', 'Fichier uploadé avec succès !');
                return new RedirectResponse($request->getUri());
            }
        }

        return $this->render('file/upload.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
