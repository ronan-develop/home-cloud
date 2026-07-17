<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Interface\FileRepositoryInterface;
use App\Interface\MediaRepositoryInterface;
use App\Interface\ShareLinkAccessCheckerInterface;
use App\Interface\StorageServiceInterface;
use App\Service\MediaFullResponseFactory;
use App\Security\ResourceLocator;
use App\Security\SharedFileScopeChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Consultation d'une ressource via un lien de partage public — sans compte,
 * sans session. Le secret est entièrement porté par (selector, token) dans
 * l'URL ; aucune vérification ne repose sur l'utilisateur connecté.
 *
 * Volontairement : token faux, lien expiré ou révoqué renvoient tous 404
 * (jamais 403), pour ne pas confirmer à un attaquant qu'un selector existe.
 */
final class PublicShareController extends AbstractController
{
    public function __construct(
        private readonly ShareLinkAccessCheckerInterface $shareLinkAccessChecker,
        private readonly ResourceLocator $resourceLocator,
        private readonly FileRepositoryInterface $fileRepository,
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly SharedFileScopeChecker $sharedFileScopeChecker,
        private readonly StorageServiceInterface $storageService,
        private readonly MediaFullResponseFactory $mediaFullResponseFactory,
    ) {}

    #[Route('/p/{selector}/{token}', name: 'app_public_share', methods: ['GET'])]
    public function show(string $selector, string $token): Response
    {
        [$link, $resource] = $this->resolveLinkOrFail($selector, $token);

        $response = $this->render('web/public_share.html.twig', [
            'resource'     => $resource,
            'resourceType' => $link->getResourceType(),
            'resourceName' => $this->resourceName($resource),
            'link'         => $link,
            'token'        => $token,
        ]);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    #[Route('/p/{selector}/{token}/download/{fileId}', name: 'app_public_share_download', methods: ['GET'])]
    public function download(string $selector, string $token, string $fileId): StreamedResponse
    {
        [$link, $linkResource] = $this->resolveLinkOrFail($selector, $token);

        $file = $this->fileRepository->findById(Uuid::fromString($fileId))
            ?? throw new NotFoundHttpException();

        if (!$this->sharedFileScopeChecker->isInScope($file, $link->getResourceType(), $link->getResourceId(), $linkResource)) {
            throw new AccessDeniedHttpException('Ce fichier ne fait pas partie du partage.');
        }

        $absolutePath = $this->storageService->getAbsolutePath($file->getPath());

        if (!file_exists($absolutePath)) {
            throw new NotFoundHttpException();
        }

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
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /**
     * Sert le thumbnail d'un média pour la grille de vignettes de la page
     * publique — équivalent de MediaGalleryController::thumbnail() mais sans
     * compte, le contrôle passe par le lien plutôt que par la session.
     */
    #[Route('/p/{selector}/{token}/media/{mediaId}/thumbnail', name: 'app_public_share_media_thumbnail', methods: ['GET'])]
    public function mediaThumbnail(string $selector, string $token, string $mediaId): Response
    {
        [$link, $linkResource] = $this->resolveLinkOrFail($selector, $token);
        $media = $this->mediaInScopeOrFail($mediaId, $link, $linkResource);

        if ($media->getThumbnailPath() === null) {
            throw new NotFoundHttpException();
        }

        $absolutePath = $this->storageService->getAbsolutePath($media->getThumbnailPath());

        if (!file_exists($absolutePath)) {
            throw new NotFoundHttpException();
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->headers->set('Content-Type', 'image/jpeg');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    /**
     * Sert le fichier original en affichage inline pour la lightbox de la
     * page publique — équivalent de MediaGalleryController::full().
     */
    #[Route('/p/{selector}/{token}/media/{mediaId}/full', name: 'app_public_share_media_full', methods: ['GET'])]
    public function mediaFull(string $selector, string $token, string $mediaId): Response
    {
        [$link, $linkResource] = $this->resolveLinkOrFail($selector, $token);
        $media = $this->mediaInScopeOrFail($mediaId, $link, $linkResource);

        $file = $media->getFile();
        $absolutePath = $this->storageService->getAbsolutePath($file->getPath());

        if (!file_exists($absolutePath)) {
            throw new NotFoundHttpException();
        }

        // Un RAW est servi via sa preview JPEG embarquée : le navigateur ne sait
        // pas décoder le fichier d'origine, et le télécharger coûterait plusieurs
        // dizaines de Mo pour n'afficher qu'une image cassée.
        $response = $this->mediaFullResponseFactory->create($absolutePath, $file->getMimeType(), $file->getPath());
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    private function mediaInScopeOrFail(string $mediaId, \App\Entity\ShareLink $link, File|Folder|Album $linkResource): \App\Entity\Media
    {
        $media = $this->mediaRepository->findById(Uuid::fromString($mediaId))
            ?? throw new NotFoundHttpException();

        if (!$this->sharedFileScopeChecker->isInScope($media->getFile(), $link->getResourceType(), $link->getResourceId(), $linkResource)) {
            throw new AccessDeniedHttpException('Ce média ne fait pas partie du partage.');
        }

        return $media;
    }

    private function resourceName(File|Folder|Album $resource): string
    {
        return match (true) {
            $resource instanceof File   => $resource->getOriginalName(),
            $resource instanceof Folder => $resource->getName(),
            $resource instanceof Album  => $resource->getName(),
        };
    }

    /**
     * @return array{0: \App\Entity\ShareLink, 1: File|Folder|Album}
     */
    private function resolveLinkOrFail(string $selector, string $token): array
    {
        $link = $this->shareLinkAccessChecker->resolve($selector, $token);

        if ($link === null) {
            throw new NotFoundHttpException();
        }

        // ResourceLocator::locate() lève déjà NotFoundHttpException si la
        // ressource a été supprimée depuis la création du lien — pas de
        // traitement supplémentaire nécessaire, la 404 se propage telle quelle.
        $resource = $this->resourceLocator->locate($link->getResourceType(), $link->getResourceId());

        return [$link, $resource];
    }
}
