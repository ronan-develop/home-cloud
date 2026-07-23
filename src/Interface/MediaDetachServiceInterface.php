<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Media;

/**
 * Détache un Media de son File source (#246) : supprime uniquement le
 * fichier physique et son entité, en conservant le Media (et ses
 * appartenances aux albums via AlbumMedia) intact.
 */
interface MediaDetachServiceInterface
{
    /**
     * @throws \LogicException si le Media est déjà détaché
     */
    public function detachAndDeleteFile(Media $media): void;
}
