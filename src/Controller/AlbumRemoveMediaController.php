<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AlbumRepository;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Retire un média d'un album.
 *
 * Rôle : gérer DELETE /api/v1/albums/{id}/medias/{mediaId}.
 * Supprime uniquement l'association — le Media (et son File) ne sont pas supprimés.
 */
#[AsController]
#[Route('/api/v1/albums/{id}/medias/{mediaId}', name: 'album_remove_media', methods: ['DELETE'])]
final class AlbumRemoveMediaController extends AbstractController
{
    public function __construct(
        private readonly AlbumRepository $albumRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(string $id, string $mediaId): JsonResponse
    {
        $album = $this->albumRepository->find($id)
            ?? throw new NotFoundHttpException('Album not found');

        $media = $this->mediaRepository->find($mediaId)
            ?? throw new NotFoundHttpException('Media not found');

        $album->removeMedia($media);
        $this->em->flush();

        return new JsonResponse(['id' => (string) $album->getId(), 'mediaCount' => $album->getMedias()->count()], Response::HTTP_OK);
    }
}
