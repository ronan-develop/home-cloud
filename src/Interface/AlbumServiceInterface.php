<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;

interface AlbumServiceInterface
{
    /**
     * @param string[] $mediaIds
     * @throws \InvalidArgumentException si le nom est vide ou uniquement des espaces
     */
    public function create(string $name, User $owner, array $mediaIds = []): Album;

    /**
     * @param string[] $mediaIds
     */
    public function addMedias(Album $album, array $mediaIds, User $owner): void;

    public function removeMedia(Album $album, Media $media): void;

    /**
     * @param string[] $mediaIds
     */
    public function reorder(Album $album, array $mediaIds): void;

    public function delete(Album $album): void;
}
