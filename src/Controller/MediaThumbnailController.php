<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\MediaRepository;
use App\Service\StorageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller dédié au téléchargement des thumbnails média.
 *
 * Rôle : streamer le thumbnail depuis le disque avec le Content-Type approprié.
 *
 * Choix :
 * - Retourne 404 si aucun Media n'existe pour cet ID ou si le thumbnailPath est null
 *   (Media traité mais GD absent au moment du traitement).
 * - Séparé de FileDownloadController car la logique de résolution est différente
 *   (Media → thumbnailPath vs File → path).
 */
final class MediaThumbnailController extends AbstractController
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly StorageService $storageService,
    ) {}

    #[Route('/api/v1/medias/{id}/thumbnail', name: 'media_thumbnail', methods: ['GET'])]
    public function __invoke(string $id): Response
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

        return new Response(
            file_get_contents($absolutePath),
            Response::HTTP_OK,
            ['Content-Type' => 'image/jpeg'],
        );
    }
}
