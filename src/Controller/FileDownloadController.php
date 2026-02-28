<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\FileRepository;
use App\Interface\StorageServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller dédié au téléchargement des fichiers uploadés.
 *
 * Rôle : streamer le fichier binaire depuis le disque avec les headers appropriés
 * (Content-Type, Content-Disposition).
 *
 * Sécurité :
 * - Les fichiers ordinaires sont stockés en clair ; les fichiers neutralisés sont stockés
 *   en .bin avec leur contenu d'origine intact. Dans les deux cas, le fichier est streamé
 *   directement — aucun déchiffrement nécessaire.
 * - Content-Disposition utilise l'originalName stocké en DB pour restituer le vrai nom.
 * - X-Content-Type-Options: nosniff empêche le MIME sniffing navigateur.
 *
 * ⚠️ Tests : StreamedResponse retourne un body vide dans le client PHPUnit.
 * Les tests vérifient status + headers uniquement.
 */
final class FileDownloadController extends AbstractController
{
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly StorageServiceInterface $storageService,
    ) {}

    #[Route('/api/v1/files/{id}/download', name: 'file_download', methods: ['GET'])]
    public function __invoke(string $id): StreamedResponse
    {
        $file = $this->fileRepository->find($id)
            ?? throw new NotFoundHttpException('File not found');

        $absolutePath = $this->storageService->getAbsolutePath($file->getPath());

        if (!file_exists($absolutePath)) {
            throw new NotFoundHttpException('Physical file not found');
        }

        // MIME détecté depuis le fichier sur disque — pas de confiance au MIME DB (spoofable)
        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath) ?: 'application/octet-stream';

        $disposition = HeaderUtils::makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $file->getOriginalName(),
        );

        $response = new StreamedResponse(function () use ($absolutePath): void {
            readfile($absolutePath);
        });

        $response->headers->set('Content-Type', $detectedMime);
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
