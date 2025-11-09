<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoMimeTypeValidator
{
    /**
     * @param string[] $allowedMimeTypes
     */
    public function __construct(private readonly array $allowedMimeTypes) {}

    public function validate(UploadedFile $file): void
    {
        $mimeType = $file->getClientMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new \InvalidArgumentException('Le fichier uploadé doit être une image ou un format RAW supporté.');
        }
    }
}
