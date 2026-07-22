<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Interface\AlbumRepositoryInterface;
use App\Repository\MediaRepository;
use App\Security\AlbumVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

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
        private readonly AlbumRepositoryInterface $albumRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(string $id, string $mediaId): JsonResponse
    {
        $album = $this->albumRepository->findById(Uuid::fromString($id))
            ?? throw new NotFoundHttpException('Album not found');

        $this->denyAccessUnlessGranted(AlbumVoter::VIEW, $album);

        $media = $this->mediaRepository->find($mediaId)
            ?? throw new NotFoundHttpException('Media not found');

        /** @var User $user */
        $user = $this->getUser();
        if (!$media->isOwnedBy($user)) {
            throw new AccessDeniedHttpException('You are not the owner of this Media');
        }

        $album->removeMedia($media);
        $this->em->flush();

        return new JsonResponse(['id' => (string) $album->getId(), 'mediaCount' => $album->getMedias()->count()], Response::HTTP_OK);
    }
}
