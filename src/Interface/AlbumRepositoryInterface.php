<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Album;
use App\Entity\User;
use Symfony\Component\Uid\Uuid;

interface AlbumRepositoryInterface
{
    /** @return Album[] */
    public function findByOwner(User $user): array;

    public function findById(Uuid $id): ?Album;

    public function save(Album $album): void;

    public function remove(Album $album): void;

    /**
     * Noms des albums contenant chacun des médias donnés, groupés par média.
     *
     * @param Uuid[] $mediaIds
     * @return array<string, string[]> mediaId (RFC4122) => noms d'albums, dans
     *                                  l'ordre de création de l'album (le plus
     *                                  ancien en premier).
     */
    public function findAlbumNamesByMediaIds(array $mediaIds): array;
}
