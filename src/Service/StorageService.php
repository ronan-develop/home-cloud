<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\StorageServiceInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Gère le stockage physique des fichiers uploadés sur le disque local.
 *
 * Rôle : encapsuler toute la logique d'écriture sur disque — génération du chemin,
 * création des répertoires, déplacement du fichier temporaire.
 *
 * Choix :
 * - Stockage dans `var/storage/{year}/{month}/{uuid}.{ext}`.
 * - Retourne un chemin relatif à `var/storage/` pour que l'entité soit
 *   indépendante de l'emplacement absolu du projet.
 * - Pas de lib externe (VichUploader, Flysystem) — contrôle total, dépendances minimales.
 * - Neutralisation ciblée : les fichiers dangereux (scripts, SVG, HTML…) sont renommés
 *   en .bin pour les rendre non-exécutables. Les fichiers ordinaires sont stockés en clair.
 */
final class StorageService implements StorageServiceInterface
{
    /**
     * Extensions neutralisées : renommées en .bin sur disque (contenu intact,
     * extension empêche l'interprétation par le serveur web ou le navigateur).
     */
    private const NEUTRALIZED_EXTENSIONS = [
        // Scripts interprétés côté serveur ou shell
        'sh', 'bash', 'zsh', 'fish', 'ksh', 'csh', 'py', 'rb', 'pl', 'cgi',
        // Markup/scripts actifs côté navigateur
        'svg', 'svgz', 'html', 'htm', 'xhtml',
        'js', 'mjs', 'css',
        'xml', 'xsl', 'xslt',
    ];

    public function __construct(
        private readonly string $storageDir,
    ) {}

    /**
     * Déplace le fichier uploadé vers le stockage permanent.
     *
     * Les fichiers à extension dangereuse (scripts, SVG, HTML…) sont neutralisés :
     * renommés en .bin pour empêcher toute exécution serveur ou interprétation navigateur.
     * Les fichiers ordinaires sont stockés tels quels.
     *
     * @param UploadedFile $file Fichier reçu via multipart/form-data
     * @return array{path: string, neutralized: bool}
     */
    public function store(UploadedFile $file): array
    {
        $year = date('Y');
        $month = date('m');
        $uuid = \Symfony\Component\Uid\Uuid::v7()->toRfc4122();
        $originalExt = strtolower($file->getClientOriginalExtension() ?? $file->guessExtension() ?? 'bin');

        $neutralized = in_array($originalExt, self::NEUTRALIZED_EXTENSIONS, true);
        $ext = $neutralized ? 'bin' : ($file->guessExtension() ?? $originalExt);

        $subDir = sprintf('%s/%s', $year, $month);
        $filename = sprintf('%s.%s', $uuid, $ext);

        $file->move($this->storageDir.'/'.$subDir, $filename);

        return [
            'path'       => sprintf('%s/%s', $subDir, $filename),
            'neutralized' => $neutralized,
        ];
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
     *
     * Sécurité : vérifie que le chemin résolu reste bien sous $storageDir
     * pour prévenir toute attaque par path traversal (ex: "../../etc/passwd").
     *
     * @throws \RuntimeException si le chemin sort du répertoire de stockage
     */
    public function getAbsolutePath(string $relativePath): string
    {
        $candidate = $this->storageDir.'/'.$relativePath;
        $resolved  = realpath($candidate);

        if ($resolved === false) {
            return $candidate;
        }

        $storageReal = realpath($this->storageDir);

        if ($storageReal === false || !str_starts_with($resolved, $storageReal.DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException(sprintf('Path "%s" is outside the storage directory.', $relativePath));
        }

        return $resolved;
    }
}
