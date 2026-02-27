<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Contrat pour le stockage physique des fichiers uploadés.
 *
 * Dépendre de cette interface permet de swapper le backend de stockage
 * (filesystem local, S3, FTP o2switch…) sans toucher aux consommateurs.
 */
interface StorageServiceInterface
{
    /**
     * Stocke le fichier uploadé sur disque et retourne le chemin relatif.
     */
    public function store(UploadedFile $file): string;

    /**
     * Supprime le fichier identifié par son chemin relatif.
     */
    public function delete(string $relativePath): void;

    /**
     * Retourne le chemin absolu à partir d'un chemin relatif.
     */
    public function getAbsolutePath(string $relativePath): string;
}
