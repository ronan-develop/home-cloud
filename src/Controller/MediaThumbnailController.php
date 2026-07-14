<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Share;
use App\Entity\User;
use App\Repository\MediaRepository;
use App\Interface\StorageServiceInterface;
use App\Security\ShareAccessChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller dédié au téléchargement des thumbnails média.
 *
 * Rôle : streamer le thumbnail depuis le disque avec le Content-Type approprié.
 *
 * Sécurité :
 * - Le propriétaire du média peut toujours voir sa vignette ; un autre
 *   utilisateur ne le peut que s'il bénéficie d'un partage actif sur le File
 *   sous-jacent (pas de Share::RESOURCE_MEDIA distinct — la vignette est une
 *   représentation dérivée du fichier).
 * - Les thumbnails sont stockés en clair — streaming direct sans déchiffrement.
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
#[IsGranted('ROLE_USER')]
final class MediaThumbnailController extends AbstractController
{
    public function __construct(
        private readonly MediaRepository $mediaRepository,
        private readonly StorageServiceInterface $storageService,
        private readonly ShareAccessChecker $shareAccessChecker,
    ) {}

    #[Route('/api/v1/medias/{id}/thumbnail', name: 'media_thumbnail', methods: ['GET'])]
    public function __invoke(string $id): StreamedResponse
    {
        $media = $this->mediaRepository->find($id)
            ?? throw new NotFoundHttpException('Media not found');

        /** @var User $user */
        $user = $this->getUser();
        if (!$media->isOwnedBy($user)
            && !$this->shareAccessChecker->canAccess($user, Share::RESOURCE_FILE, $media->getFile()->getId())) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir ce média.');
        }

        if ($media->getThumbnailPath() === null) {
            throw new NotFoundHttpException('No thumbnail available for this media');
        }

        $absolutePath = $this->storageService->getAbsolutePath($media->getThumbnailPath());

        if (!file_exists($absolutePath)) {
            throw new NotFoundHttpException('Thumbnail file not found on disk');
        }

        $response = new StreamedResponse(function () use ($absolutePath): void {
            readfile($absolutePath);
        });

        $response->headers->set('Content-Type', 'image/jpeg');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
