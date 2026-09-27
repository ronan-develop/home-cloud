<?php

declare(strict_types=1);

namespace App\Service\Takeout;

/**
 * Parcourt l'arborescence extraite d'un export Google Takeout, distingue
 * médias / métadonnées / fichiers à ignorer, associe chaque média à son
 * <nom>.supplemental-metadata.json s'il existe.
 *
 * Un seul parcours disque (#327, contrainte d'efficacité) : classification
 * et association se font dans le même passage — jamais un passage
 * "classification" suivi d'un passage "lecture" qui relirait deux fois
 * l'arborescence potentiellement volumineuse d'un export complet.
 */
class TakeoutStructureParser
{
    private const IGNORED_FILENAMES = [
        'metadata.json',
        'print-subscriptions.json',
        'shared_album_comments.json',
        'user-generated-memory-titles.json',
    ];

    private const METADATA_SUFFIX = '.supplemental-metadata.json';

    // Google Photos range les médias sans album explicite dans un dossier
    // technique "Photos from <année>" — jamais une intention consciente de
    // l'utilisateur, ne doit jamais devenir un album (#478).
    private const PHOTOS_FROM_YEAR_PATTERN = '/^Photos from \d{4}$/';

    public function parse(string $root): TakeoutStructureResult
    {
        $filesByDir = [];
        $ignoredCount = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        // Un seul parcours : on regroupe par dossier pendant l'itération pour
        // pouvoir ensuite associer média ↔ json sans jamais relire le disque.
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $dir = $fileInfo->getPath();
            $filesByDir[$dir][] = $fileInfo->getFilename();
        }

        $mediaEntries = [];

        foreach ($filesByDir as $dir => $filenames) {
            $albumName = $this->resolveAlbumName($root, $dir);
            $metadataByMediaName = [];
            $candidateMediaNames = [];

            foreach ($filenames as $filename) {
                if (in_array($filename, self::IGNORED_FILENAMES, true)) {
                    ++$ignoredCount;
                    continue;
                }

                if (str_ends_with($filename, self::METADATA_SUFFIX)) {
                    $mediaName = substr($filename, 0, -strlen(self::METADATA_SUFFIX));
                    $metadataByMediaName[$mediaName] = $filename;
                    continue;
                }

                $candidateMediaNames[] = $filename;
            }

            foreach ($candidateMediaNames as $mediaName) {
                $metadataFilename = $metadataByMediaName[$mediaName] ?? null;
                $mediaEntries[] = new TakeoutMediaEntry(
                    mediaPath: $dir . '/' . $mediaName,
                    metadataPath: $metadataFilename !== null ? $dir . '/' . $metadataFilename : null,
                    albumName: $albumName,
                );
            }
        }

        return new TakeoutStructureResult($mediaEntries, $ignoredCount);
    }

    /**
     * Un vrai album Google Photos est un sous-dossier nommé directement sous
     * la racine de l'export (ex: "Google Photos/Vacances 2026/") — jamais un
     * média directement à la racine, ni un dossier technique "Photos from
     * <année>" (#478).
     */
    private function resolveAlbumName(string $root, string $dir): ?string
    {
        $relative = ltrim(substr($dir, strlen($root)), '/');
        $segments = explode('/', $relative);

        // Un média à la racine du Google Photos exporté (pas de sous-dossier
        // nommé) n'appartient à aucun album.
        if (count($segments) < 2) {
            return null;
        }

        $candidate = end($segments);

        if (preg_match(self::PHOTOS_FROM_YEAR_PATTERN, $candidate) === 1) {
            return null;
        }

        return $candidate;
    }
}
