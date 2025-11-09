<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoMimeTypeValidator
{
    /**
     * Validation statique sans dépendance à l'instance.
     * @param UploadedFile $file
     * @param string[] $allowedMimeTypes
     */
    public static function validateStatic(UploadedFile $file, array $allowedMimeTypes): void
    {
        $mimeType = $file->getClientMimeType();
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            $allowed = implode(', ', $allowedMimeTypes);
            throw new \InvalidArgumentException(
                sprintf(
                    "Type MIME refusé : '%s'. Types acceptés : %s",
                    $mimeType,
                    $allowed
                )
            );
        }
    }  
    /**
     * @param string[] $allowedMimeTypes
     */
    public function __construct(private readonly array $allowedMimeTypes) {}

    public function validate(UploadedFile $file): void
    {
        $mimeType = $file->getClientMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            $allowed = implode(', ', $this->allowedMimeTypes);
            throw new \InvalidArgumentException(
                sprintf(
                    "Type MIME refusé : '%s'. Types acceptés : %s",
                    $mimeType,
                    $allowed
                )
            );
        }
    }
}
