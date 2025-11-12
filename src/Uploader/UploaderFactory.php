<?php

namespace App\Uploader;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploaderFactory
{
    /**
     * @var UploaderInterface[]
     */
    private array $uploaders;

    /**
     * @param UploaderInterface[] $uploaders
     */
    public function __construct(iterable $uploaders)
    {
        $this->uploaders = [];
        foreach ($uploaders as $uploader) {
            $this->uploaders[] = $uploader;
        }
    }

    /**
     * Retourne l'uploader adapté selon le contexte (type ou support)
     * @param UploadedFile $file
     * @param array $context
     * @return UploaderInterface
     */
    public function getUploader(UploadedFile $file, array $context = []): UploaderInterface
    {
        foreach ($this->uploaders as $uploader) {
            if ($uploader->supports($file, $context)) {
                return $uploader;
            }
        }
        throw new \InvalidArgumentException('Aucun uploader ne supporte ce fichier.');
    }
}
