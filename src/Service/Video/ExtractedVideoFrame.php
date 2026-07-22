<?php

declare(strict_types=1);

namespace App\Service\Video;

/**
 * Frame extraite d'une vidéo, en JPEG.
 *
 * Pas d'orientation ici, contrairement à {@see \App\Service\ExifThumbnail} :
 * ffmpeg applique lui-même la métadonnée `rotate` des conteneurs MP4 au
 * décodage, la frame produite est donc déjà droite.
 */
final readonly class ExtractedVideoFrame
{
    public function __construct(
        public string $jpegData,
    ) {}
}
