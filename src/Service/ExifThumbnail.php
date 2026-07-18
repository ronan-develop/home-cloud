<?php

declare(strict_types=1);

namespace App\Service;

use RonanLenouvel\RawPreviewExtractor\Orientation;

/**
 * Miniature EXIF extraite, avec l'orientation enregistrée par l'appareil.
 *
 * La miniature est stockée telle quelle par l'appareil, non redressée — même
 * logique que {@see \RonanLenouvel\RawPreviewExtractor\ExtractedPreview} pour
 * les RAW : c'est à l'appelant d'appliquer la rotation.
 */
final readonly class ExifThumbnail
{
    public function __construct(
        public string $jpegData,
        public Orientation $orientation,
    ) {}
}
