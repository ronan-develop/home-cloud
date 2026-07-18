<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\ExifThumbnailExtractorInterface;
use RonanLenouvel\RawPreviewExtractor\Orientation;

/**
 * Implémentation réelle : lit la miniature IFD1 et l'orientation via
 * l'extension native `ext-exif` (déjà une dépendance requise du projet).
 */
final class ExifThumbnailExtractor implements ExifThumbnailExtractorInterface
{
    public function extract(string $absolutePath): ?ExifThumbnail
    {
        if (!function_exists('exif_thumbnail')) {
            return null;
        }

        $jpegData = @exif_thumbnail($absolutePath);
        if ($jpegData === false) {
            return null;
        }

        $exif = @exif_read_data($absolutePath);
        $orientationTag = is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : null;

        return new ExifThumbnail($jpegData, Orientation::fromExif($orientationTag));
    }
}
