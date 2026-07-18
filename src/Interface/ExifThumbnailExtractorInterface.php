<?php

declare(strict_types=1);

namespace App\Interface;

use App\Service\ExifThumbnail;

/**
 * Extrait la miniature embarquée dans les métadonnées EXIF d'un JPEG.
 *
 * La plupart des JPEG issus d'un appareil photo ou d'un scanner embarquent
 * déjà une petite miniature (souvent 160x120) dans leur IFD1. La récupérer
 * évite de décoder l'image pleine résolution avec GD juste pour en tirer une
 * vignette de 320px — un décodage qui, sur un scan haute résolution, peut à
 * lui seul saturer la mémoire allouée au worker.
 */
interface ExifThumbnailExtractorInterface
{
    /**
     * @param string $absolutePath Chemin absolu du JPEG source
     */
    public function extract(string $absolutePath): ?ExifThumbnail;
}
