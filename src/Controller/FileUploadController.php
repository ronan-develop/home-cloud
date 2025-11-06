<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FileUploadController extends AbstractController
{
    /**
     * @Route("/files/upload", name="file_upload", methods={"POST"})
     * @IsGranted("IS_AUTHENTICATED_FULLY")
     */
    public function upload(Request $request): Response
    {
        $file = $request->files->get('file');
        if (!$file) {
            return new JsonResponse(['error' => 'Aucun fichier envoyé.'], Response::HTTP_BAD_REQUEST);
        }

        // Exclure images/photos
        $forbiddenMime = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/svg+xml',
            'image/tiff',
            'image/x-icon'
        ];
        if (in_array($file->getMimeType(), $forbiddenMime, true)) {
            return new JsonResponse(['error' => 'Les images/photos ne sont pas autorisées.'], Response::HTTP_FORBIDDEN);
        }

        // Stockage du fichier (exemple, à adapter)
        $uploadDir = $this->getParameter('kernel.project_dir') . '/var/data/files';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $file->move($uploadDir, $filename);

        // TODO: Enregistrer les métadonnées en base (nom, chemin, taille, type, user...)

        return new JsonResponse([
            'success' => true,
            'filename' => $filename,
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ]);
    }
}
