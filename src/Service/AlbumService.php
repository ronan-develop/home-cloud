<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Album;
use App\Entity\User;
use App\Interface\AlbumRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Service métier pour la gestion des albums (SRP).
 *
 * Responsabilité unique : orchestrer la création et la suppression d'albums,
 * avec validation des règles métier (nom non vide).
 * La persistence est déléguée à AlbumRepositoryInterface (DIP).
 */
final class AlbumService
{
    public function __construct(
        private readonly AlbumRepositoryInterface $repository,
    ) {}

    /**
     * Crée un nouvel album pour l'utilisateur donné.
     *
     * @throws BadRequestHttpException si le nom est vide ou uniquement des espaces
     */
    public function create(string $name, User $owner): Album
    {
        $name = trim($name);
        if ($name === '') {
            throw new BadRequestHttpException('Le nom de l\'album est obligatoire.');
        }

        $album = new Album($name, $owner);
        $this->repository->save($album);

        return $album;
    }

    /**
     * Supprime un album (et ses associations album_media — pas les médias eux-mêmes).
     */
    public function delete(Album $album): void
    {
        $this->repository->remove($album);
    }
}
