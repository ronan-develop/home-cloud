<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\FileRepository;
use App\Service\StorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller dédié au téléchargement des fichiers uploadés.
 *
 * Rôle : streamer le fichier binaire depuis le disque avec les headers appropriés
 * (Content-Type, Content-Disposition).
 *
 * Sécurité :
 * - Le Content-Type est revalidé depuis le disque via finfo (pas de confiance au
 *   MIME stocké en DB, qui vient du client) pour éviter le content-type spoofing.
 * - X-Content-Type-Options: nosniff empêche le MIME sniffing navigateur.
 * - BinaryFileResponse streame le fichier sans le charger entièrement en RAM.
 *
 * ⚠️ Tests : BinaryFileResponse retourne un body vide dans le client PHPUnit
 * (il ne lit pas le disque). Les tests vérifient status + headers uniquement.
 */
final class FileDownloadController extends AbstractController
{
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly StorageService $storageService,
    ) {}

    #[Route('/api/v1/files/{id}/download', name: 'file_download', methods: ['GET'])]
    public function __invoke(string $id): BinaryFileResponse
    {
        $file = $this->fileRepository->find($id)
            ?? throw new NotFoundHttpException('File not found');

        $absolutePath = $this->storageService->getAbsolutePath($file->getPath());

        if (!file_exists($absolutePath)) {
            throw new NotFoundHttpException('Physical file not found');
        }

        // Revalider le MIME depuis le disque — ne pas faire confiance à la valeur en DB
        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath)
            ?: 'application/octet-stream';

        $response = new BinaryFileResponse($absolutePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $file->getOriginalName(),
        );
        $response->headers->set('Content-Type', $detectedMime);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
