<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Interface\AlbumRepositoryInterface;
use App\Repository\MediaRepository;
use App\Security\AlbumVoter;
use App\State\AlbumProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Ajoute un média à un album.
 *
 * Rôle : gérer POST /api/v1/albums/{id}/medias hors du pipeline API Platform
 * (requête JSON simple, pas de désérialisation de ressource).
 *
 * Idempotent : si le média est déjà dans l'album, retourne 200 sans doublon.
 */
#[AsController]
#[Route('/api/v1/albums/{id}/medias', name: 'album_add_media', methods: ['POST'])]
final class AlbumAddMediaController extends AbstractController
{
    public function __construct(
        private readonly AlbumRepositoryInterface $albumRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly EntityManagerInterface $em,
        private readonly AlbumProvider $provider,
    ) {}

    public function __invoke(string $id, Request $request): JsonResponse
    {
        $album = $this->albumRepository->findById(Uuid::fromString($id))
            ?? throw new NotFoundHttpException('Album not found');

        $this->denyAccessUnlessGranted(AlbumVoter::VIEW, $album);

        $body = json_decode((string) $request->getContent(), true);
        $mediaId = $body['mediaId'] ?? null;

        if (empty($mediaId)) {
            throw new BadRequestHttpException('mediaId is required');
        }

        $media = $this->mediaRepository->find($mediaId)
            ?? throw new NotFoundHttpException('Media not found');

        /** @var User $user */
        $user = $this->getUser();
        if (!$media->isOwnedBy($user)) {
            throw new AccessDeniedHttpException('You are not the owner of this Media');
        }

        $album->addMedia($media); // idempotent via Collection::contains()
        $this->em->flush();

        return new JsonResponse($this->toArray($album->getMedias()->count(), (string) $album->getId()), Response::HTTP_OK);
    }

    private function toArray(int $mediaCount, string $albumId): array
    {
        return ['id' => $albumId, 'mediaCount' => $mediaCount];
    }
}
