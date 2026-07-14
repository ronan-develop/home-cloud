<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\Share;
use App\Entity\User;
use App\Interface\AlbumRepositoryInterface;
use App\Interface\AlbumServiceInterface;
use App\Interface\MediaRepositoryInterface;
use App\Interface\ShareRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * Service métier pour la gestion des albums (SRP).
 *
 * Responsabilité unique : orchestrer la création et la suppression d'albums,
 * avec validation des règles métier (nom non vide).
 * La persistence est déléguée à AlbumRepositoryInterface (DIP).
 */
final class AlbumService implements AlbumServiceInterface
{
    public function __construct(
        private readonly AlbumRepositoryInterface $repository,
        private readonly MediaRepositoryInterface $mediaRepository,
        private readonly ShareRepositoryInterface $shareRepository,
    ) {}

    /**
     * Crée un nouvel album pour l'utilisateur donné, avec une sélection
     * optionnelle de médias à y ajouter immédiatement.
     *
     * Les identifiants invalides, introuvables ou n'appartenant pas à $owner
     * sont ignorés silencieusement (pas d'erreur bloquante sur la création).
     *
     * @param string[] $mediaIds
     * @throws InvalidArgumentException si le nom est vide ou uniquement des espaces
     */
    public function create(string $name, User $owner, array $mediaIds = []): Album
    {
        $album = new Album($name, $owner);
        $this->addOwnedMediasTo($album, $mediaIds, $owner);
        $this->repository->save($album);

        return $album;
    }

    /**
     * Ajoute une sélection de médias à un album existant.
     *
     * Les identifiants invalides, introuvables ou n'appartenant pas à $owner
     * sont ignorés silencieusement (idempotent, pas d'erreur bloquante).
     *
     * @param string[] $mediaIds
     */
    public function addMedias(Album $album, array $mediaIds, User $owner): void
    {
        $this->addOwnedMediasTo($album, $mediaIds, $owner);
        $this->repository->save($album);
    }

    /**
     * Retire un média d'un album. Ne supprime que l'association — le Media
     * (et son File) restent intacts et disponibles dans la galerie.
     */
    public function removeMedia(Album $album, Media $media): void
    {
        $album->removeMedia($media);
        $this->repository->save($album);
    }

    /**
     * Applique un nouvel ordre d'affichage aux médias de l'album.
     * Les identifiants invalides ou hors de l'album sont ignorés silencieusement.
     *
     * @param string[] $mediaIds Nouvel ordre complet, du premier au dernier.
     */
    public function reorder(Album $album, array $mediaIds): void
    {
        $uuids = [];
        foreach ($mediaIds as $mediaId) {
            try {
                $uuids[] = Uuid::fromString($mediaId);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        $album->reorder($uuids);
        $this->repository->save($album);
    }

    /**
     * Supprime un album (et ses associations album_media — pas les médias eux-mêmes).
     */
    public function delete(Album $album): void
    {
        $this->shareRepository->deleteByResource(Share::RESOURCE_ALBUM, $album->getId());
        $this->repository->remove($album);
    }

    /**
     * @param string[] $mediaIds
     */
    private function addOwnedMediasTo(Album $album, array $mediaIds, User $owner): void
    {
        foreach ($mediaIds as $mediaId) {
            try {
                $uuid = Uuid::fromString($mediaId);
            } catch (\InvalidArgumentException) {
                continue;
            }

            $media = $this->mediaRepository->findById($uuid);
            if ($media !== null && $media->isOwnedBy($owner)) {
                $album->addMedia($media);
            }
        }
    }
}
