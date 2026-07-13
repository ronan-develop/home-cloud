<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Media;

/**
 * Supprime définitivement un média : fichier original, thumbnail, et
 * l'entité elle-même (le File et les associations AlbumMedia sont retirés
 * en cascade au niveau DB — voir Media::$file et AlbumMedia::$media,
 * onDelete: CASCADE).
 */
interface MediaDeletionServiceInterface
{
    public function delete(Media $media): void;
}
