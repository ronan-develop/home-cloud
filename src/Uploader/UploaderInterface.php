<?php

namespace App\Uploader;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface UploaderInterface
{
    /**
     * Vérifie si cet uploader prend en charge le fichier (mime, extension, contexte…)
     */
    public function supports(UploadedFile $file, array $context = []): bool;

    /**
     * Effectue l’upload du fichier et retourne le résultat métier (entité, tableau, etc.)
     */
    public function upload(UploadedFile $file, array $context = []): mixed;

    /**
     * Retourne le répertoire cible de l’upload
     */
    public function getTargetDirectory(): string;
}
