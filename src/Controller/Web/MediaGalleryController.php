<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Interface\MediaRepositoryInterface;
use App\Interface\StorageServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Galerie médias — affiche les photos et vidéos de l'utilisateur.
 * Supporte le filtrage par type (?type=photo|video).
 */
#[IsGranted('ROLE_USER')]
final class MediaGalleryController extends AbstractController
{
    public function __construct(
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly StorageServiceInterface $storageService,
    ) {}

    #[Route('/gallery', name: 'app_gallery')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $type = $request->query->get('type');
        $order = $request->query->all('order');

        $medias = $this->mediaRepository->findByOwner($user, $type ?: null, $order);

        return $this->render('web/gallery.html.twig', [
            'medias' => $medias,
            'type'   => $type,
            'order'  => $order,
        ]);
    }

    #[Route('/gallery/{id}', name: 'app_media_view', requirements: ['id' => '[0-9a-f\-]+'])]
    public function view(string $id): Response
    {
        $media = $this->mediaRepository->findById(\Symfony\Component\Uid\Uuid::fromString($id));

        if ($media === null || !$media->isOwnedBy($this->getUser())) {
            throw $this->createNotFoundException('Média introuvable.');
        }

        return $this->render('web/gallery.html.twig', [
            'medias' => [$media],
            'type'   => null,
            'order'  => [],
        ]);
    }

    /**
     * Sert le thumbnail d'un média via la session web (cookie), à la différence de
     * /api/v1/medias/{id}/thumbnail qui exige un JWT (firewall api stateless) — une
     * balise <img> du navigateur n'envoie jamais ce header, cette route existe donc
     * pour que les vignettes s'affichent réellement dans /gallery et /albums/{id}.
     */
    #[Route('/gallery/{id}/thumbnail', name: 'app_media_thumbnail', requirements: ['id' => '[0-9a-f\-]+'], methods: ['GET'])]
    public function thumbnail(string $id): Response
    {
        $media = $this->mediaRepository->findById(Uuid::fromString($id));

        if ($media === null || !$media->isOwnedBy($this->getUser())) {
            throw $this->createNotFoundException('Média introuvable.');
        }

        if ($media->getThumbnailPath() === null) {
            throw $this->createNotFoundException('Aucun thumbnail pour ce média.');
        }

        $absolutePath = $this->storageService->getAbsolutePath($media->getThumbnailPath());

        if (!file_exists($absolutePath)) {
            throw $this->createNotFoundException('Thumbnail introuvable sur le disque.');
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->headers->set('Content-Type', 'image/jpeg');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /**
     * Sert le fichier original en affichage inline (pas de téléchargement forcé),
     * pour la lightbox de la galerie. Distinct de app_file_download (Content-
     * Disposition: attachment), qui déclencherait un téléchargement au lieu
     * d'afficher l'image dans le <img> de la lightbox.
     */
    #[Route('/gallery/{id}/full', name: 'app_media_full', requirements: ['id' => '[0-9a-f\-]+'], methods: ['GET'])]
    public function full(string $id): Response
    {
        $media = $this->mediaRepository->findById(Uuid::fromString($id));

        if ($media === null || !$media->isOwnedBy($this->getUser())) {
            throw $this->createNotFoundException('Média introuvable.');
        }

        $file = $media->getFile();
        $absolutePath = $this->storageService->getAbsolutePath($file->getPath());

        if (!file_exists($absolutePath)) {
            throw $this->createNotFoundException('Fichier introuvable sur le disque.');
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->headers->set('Content-Type', $file->getMimeType());
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setContentDisposition(\Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_INLINE);

        return $response;
    }
}
