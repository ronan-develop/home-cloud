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
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
        // Markup/scripts actifs côté navigateur
        'svg', 'svgz', 'html', 'htm', 'xhtml',
        'js', 'mjs', 'css',
        'xml', 'xsl', 'xslt',
    ];

    /**
     * MIME types dangereux détectés depuis le CONTENU réel (finfo), pour les
     * cas où l'extension client ment (ex: script PHP renommé en .jpg) et où
     * Symfony ne mappe pas ce MIME vers une extension via guessExtension().
     */
    private const NEUTRALIZED_MIME_TYPES = [
        'text/x-php', 'application/x-httpd-php',
        'text/x-python', 'text/x-shellscript', 'application/x-sh',
        'text/html', 'application/xhtml+xml',
        'image/svg+xml',
        'text/javascript', 'application/javascript', 'text/css',
    ];

    /**
     * Marqueurs de contenu actif PDF (#286) : le format PDF peut embarquer du
     * JavaScript exécutable via ces objets (/OpenAction, /AA = actions
     * automatiques à l'ouverture/l'événement, /JS/JavaScript = le script
     * lui-même, /Launch = exécution d'un programme externe). Un PDF contenant
     * l'un de ces tokens est neutralisé comme les autres types dangereux —
     * détection par recherche de motif sur le contenu brut, pas un vrai parseur
     * PDF (les objets compressés en flux /ObjStm échappent à cette détection,
     * risque résiduel documenté dans #286).
     */
    private const PDF_ACTIVE_CONTENT_MARKERS = [
        '/JavaScript', '/JS', '/OpenAction', '/AA', '/Launch',
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
        $clientExt = strtolower($file->getClientOriginalExtension());
        // Utilise l'extension client si disponible, sinon détecte depuis le contenu (finfo)
        $originalExt = $clientExt !== '' ? $clientExt : strtolower($file->guessExtension() ?? 'bin');

        // L'extension client est manipulable (ex: un script PHP renommé en
        // .jpg) : on détecte aussi l'extension via le contenu réel (finfo,
        // via guessExtension()) et le MIME réel, neutralise si l'UN des trois
        // signaux est dangereux.
        $detectedExt = strtolower($file->guessExtension() ?? '');
        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname()) ?: '';

        $neutralized = in_array($originalExt, self::NEUTRALIZED_EXTENSIONS, true)
            || in_array($detectedExt, self::NEUTRALIZED_EXTENSIONS, true)
            || in_array($detectedMime, self::NEUTRALIZED_MIME_TYPES, true);

        $isPdf = $originalExt === 'pdf' || $detectedExt === 'pdf' || $detectedMime === 'application/pdf';
        if (!$neutralized && $isPdf && $this->pdfHasActiveContent($file->getPathname())) {
            $neutralized = true;
        }

        $ext = $neutralized ? 'bin' : $originalExt;

        $subDir = sprintf('%s/%s', $year, $month);
        $filename = sprintf('%s.%s', $uuid, $ext);

        $file->move($this->storageDir.'/'.$subDir, $filename);

        return [
            'path'       => sprintf('%s/%s', $subDir, $filename),
            'neutralized' => $neutralized,
        ];
    }

    /**
     * Détecte du contenu actif (JS embarqué, action au lancement) dans un PDF
     * via une recherche de motif sur le contenu brut (#286).
     */
    private function pdfHasActiveContent(string $pathname): bool
    {
        $content = file_get_contents($pathname);
        if ($content === false) {
            return false;
        }

        // Délimité par une lettre/chiffre en négatif : un vrai nom d'objet PDF
        // (/JS, /AA…) n'est jamais suivi directement d'un caractère alphanumérique
        // qui en ferait un nom différent — sinon /JS matcherait /JSON en sous-chaîne
        // (ex: un chemin "config/JSON" cité dans le texte d'un PDF légitime, #286).
        foreach (self::PDF_ACTIVE_CONTENT_MARKERS as $marker) {
            $pattern = '/' . preg_quote($marker, '/') . '(?![A-Za-z0-9])/';
            if (preg_match($pattern, $content) === 1) {
                return true;
            }
        }

        return false;
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
