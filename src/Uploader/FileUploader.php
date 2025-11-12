<?php

namespace App\Uploader;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploader
{
    private string $targetDirectory;

    public function __construct(string $targetDirectory)
    {
        $this->targetDirectory = $targetDirectory;
    }

    /**
     * Déplace le fichier uploadé, calcule le hash, retourne les infos utiles
     * @param UploadedFile $file
     * @return array [filename, path, size, mimeType, hash]
     */
    public function upload(UploadedFile $file): array
    {
        if (!is_dir($this->targetDirectory)) {
            mkdir($this->targetDirectory, 0775, true);
        }
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $originalName = $file->getClientOriginalName();
        $size = $file->getSize();
        $mimeType = $file->getClientMimeType();
        $hash = hash_file('sha256', $file->getPathname());
        $file->move($this->targetDirectory, $filename);
        $path = $this->targetDirectory . '/' . $filename;
        return [
            'filename' => $filename,
            'originalName' => $originalName,
            'path' => $path,
            'size' => $size,
            'mimeType' => $mimeType,
            'hash' => $hash,
        ];
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }
}
