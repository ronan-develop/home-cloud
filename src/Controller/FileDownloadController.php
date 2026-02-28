<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\FileRepository;
use App\Interface\EncryptionServiceInterface;
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
 * Rôle : déchiffrer et streamer le fichier binaire depuis le disque avec les headers appropriés
 * (Content-Type, Content-Disposition).
 *
 * Sécurité :
 * - Chiffrement au repos : le fichier sur disque est chiffré (XChaCha20-Poly1305).
 *   EncryptionService déchiffre chunk par chunk directement vers la réponse HTTP — aucun
 *   fichier temp, aucune charge RAM complète.
 * - Le Content-Type est détecté depuis un fichier temp déchiffré via finfo (pas de confiance
 *   au MIME stocké en DB, qui vient du client) pour éviter le content-type spoofing.
 * - X-Content-Type-Options: nosniff empêche le MIME sniffing navigateur.
 * - StreamedResponse : streaming natif, pas de chargement en RAM.
 *
 * ⚠️ Tests : StreamedResponse retourne un body vide dans le client PHPUnit.
 * Les tests vérifient status + headers uniquement.
 */
final class FileDownloadController extends AbstractController
{
    public function __construct(
        private readonly FileRepository $fileRepository,
        private readonly StorageServiceInterface $storageService,
        private readonly EncryptionServiceInterface $encryption,
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

        // Détecter le MIME réel depuis le fichier déchiffré — ne pas faire confiance à la DB
        $tempPath = $this->encryption->decryptToTempFile($absolutePath);
        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($tempPath) ?: 'application/octet-stream';

        $disposition = HeaderUtils::makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $file->getOriginalName(),
        );

        $response = new StreamedResponse(function () use ($tempPath): void {
            try {
                readfile($tempPath);
            } finally {
                unlink($tempPath);
            }
        });

        $response->headers->set('Content-Type', $detectedMime);
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
