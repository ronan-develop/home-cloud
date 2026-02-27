<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Gère le stockage physique des fichiers uploadés sur le disque local.
 *
 * Rôle : encapsuler toute la logique d'écriture sur disque — génération du chemin,
 * création des répertoires, déplacement du fichier temporaire.
 *
 * Choix :
 * - Stockage dans `var/storage/{year}/{month}/{uuid}.{ext}` pour éviter les
 *   collisions et faciliter les purges par période.
 * - Retourne un chemin relatif à `var/storage/` pour que l'entité soit
 *   indépendante de l'emplacement absolu du projet.
 * - Pas de lib externe (VichUploader, Flysystem) — contrôle total, dépendances minimales.
 */
final class StorageService
{
    public function __construct(private readonly string $storageDir) {}

    /**
     * Déplace le fichier uploadé vers le stockage permanent.
     *
     * @param UploadedFile $file Fichier reçu via multipart/form-data
     * @return string            Chemin relatif stocké en base (ex: "2026/02/uuid.pdf")
     */
    public function store(UploadedFile $file): string
    {
        $year = date('Y');
        $month = date('m');
        $uuid = \Symfony\Component\Uid\Uuid::v7()->toRfc4122();
        $ext = $file->guessExtension() ?? $file->getClientOriginalExtension() ?? 'bin';

        $subDir = sprintf('%s/%s', $year, $month);
        $filename = sprintf('%s.%s', $uuid, $ext);

        $file->move($this->storageDir.'/'.$subDir, $filename);

        return sprintf('%s/%s', $subDir, $filename);
    }

    /**
     * Supprime le fichier physique du disque.
     *
     * @param string $relativePath Chemin relatif tel que stocké en base (ex: "2026/02/uuid.pdf")
     */
    public function delete(string $relativePath): void
    {
        $fullPath = $this->storageDir.'/'.$relativePath;

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * Retourne le chemin absolu d'un fichier à partir de son chemin relatif.
     */
    public function getAbsolutePath(string $relativePath): string
    {
        return $this->storageDir.'/'.$relativePath;
    }
}
