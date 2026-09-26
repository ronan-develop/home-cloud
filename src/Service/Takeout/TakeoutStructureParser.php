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
                );
            }
        }

        return new TakeoutStructureResult($mediaEntries, $ignoredCount);
    }
}
