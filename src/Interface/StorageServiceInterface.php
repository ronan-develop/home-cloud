<?php

declare(strict_types=1);

namespace App\Interface;

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
     * Stocke le fichier uploadé sur disque.
     *
     * Retourne un tableau avec :
     *   - 'path'       : chemin relatif stocké en base (ex: "2026/02/uuid.pdf" ou "2026/02/uuid.bin")
     *   - 'neutralized': true si le fichier a été renommé en .bin (extension dangereuse neutralisée)
     *
     * @return array{path: string, neutralized: bool}
     */
    public function store(UploadedFile $file): array;

    /**
     * Supprime le fichier identifié par son chemin relatif.
     */
    public function delete(string $relativePath): void;

    /**
     * Retourne le chemin absolu à partir d'un chemin relatif.
     */
    public function getAbsolutePath(string $relativePath): string;
}
