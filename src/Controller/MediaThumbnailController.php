<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\MediaRepository;
use App\Service\EncryptionServiceInterface;
use App\Service\StorageServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller dédié au téléchargement des thumbnails média.
 *
 * Rôle : déchiffrer et streamer le thumbnail depuis le disque avec le Content-Type approprié.
 *
 * Sécurité :
 * - Chiffrement au repos : le thumbnail sur disque est chiffré (XChaCha20-Poly1305).
 *   EncryptionService déchiffre chunk par chunk vers la réponse HTTP.
 * - X-Content-Type-Options: nosniff empêche le MIME sniffing navigateur.
 * - Content-Type forcé à image/jpeg (les thumbnails sont toujours des JPEG — ThumbnailService).
 *
 * Choix :
 * - Retourne 404 si aucun Media n'existe pour cet ID ou si le thumbnailPath est null
 *   (Media traité mais GD absent au moment du traitement).
 * - Séparé de FileDownloadController car la logique de résolution est différente
 *   (Media → thumbnailPath vs File → path).
 *
 * ⚠️ Tests : StreamedResponse retourne un body vide dans PHPUnit — tests sur status + headers uniquement.
 */
final class MediaThumbnailController extends AbstractController
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly StorageServiceInterface $storageService,
        private readonly EncryptionServiceInterface $encryption,
    ) {}

    #[Route('/api/v1/medias/{id}/thumbnail', name: 'media_thumbnail', methods: ['GET'])]
    public function __invoke(string $id): StreamedResponse
    {
        $media = $this->mediaRepository->find($id)
            ?? throw new NotFoundHttpException('Media not found');

        if ($media->getThumbnailPath() === null) {
            throw new NotFoundHttpException('No thumbnail available for this media');
        }

        $absolutePath = $this->storageService->getAbsolutePath($media->getThumbnailPath());

        if (!file_exists($absolutePath)) {
            throw new NotFoundHttpException('Thumbnail file not found on disk');
        }

        $response = new StreamedResponse(function () use ($absolutePath): void {
            $this->encryption->decryptToStream($absolutePath, fopen('php://output', 'wb'));
        });

        $response->headers->set('Content-Type', 'image/jpeg');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
