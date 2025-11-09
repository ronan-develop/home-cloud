<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoMimeTypeValidator
{
    private array $allowedMimeTypes = [
        // Images classiques
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/bmp',
        'image/svg+xml',
        'image/tiff',
        // Formats RAW photo courants
        'image/x-canon-cr2',
        'image/x-canon-crw',
        'image/x-nikon-nef',
        'image/x-sony-arw',
        'image/x-adobe-dng',
        'image/x-olympus-orf',
        'image/x-panasonic-raw',
        'image/x-fuji-raf',
        'image/x-pentax-pef',
        'image/x-samsung-srw',
        'image/x-minolta-mrw',
        'image/x-leaf-mos',
        'image/x-hasselblad-3fr',
        'image/x-hasselblad-fff',
        'image/x-kodak-dcr',
        'image/x-kodak-k25',
        'image/x-kodak-kdc',
        'image/x-mamiya-mef',
        'image/x-raw',
    ];

    public function validate(UploadedFile $file): void
    {
        $mimeType = $file->getClientMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new \InvalidArgumentException('Le fichier uploadé doit être une image ou un format RAW supporté.');
        }
    }
}
