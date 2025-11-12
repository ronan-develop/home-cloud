<?php

namespace App\Photo;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ExifExtractor
{
    public function extract(UploadedFile $file): array
    {
        $mimeType = $file->getClientMimeType();
        if (in_array($mimeType, ['image/jpeg', 'image/tiff'], true) && function_exists('exif_read_data')) {
            try {
                return @exif_read_data($file->getPathname()) ?: [];
            } catch (\Throwable $e) {
                return [];
            }
        }
        return [];
    }
}
