<?php

declare(strict_types=1);

namespace App\Service\Takeout;

use App\Entity\TakeoutImport;

/**
 * Détail de progression par ZIP d'un import Takeout (#545) — la seule
 * information persistée en base (extractedZipCount/totalZipCount) ne dit
 * pas QUELS ZIP sont terminés ni l'avancement du ZIP en cours (#546
 * introduit un batch limité par appel, donc un ZIP volumineux peut rester
 * "en cours" plusieurs minutes). Reconstruit ce détail à la demande depuis
 * le filesystem : les N premiers ZIP (dans l'ordre glob, celui utilisé au
 * démarrage par TakeoutImportStartController) sont terminés, le suivant lit
 * son checkpoint .progress, les autres n'ont pas commencé.
 */
final class TakeoutZipProgressReporter
{
    public function __construct(
        private readonly TakeoutImportTmpDirLocator $tmpDirLocator,
    ) {}

    /**
     * @return list<array{name: string, extractedEntries: int, totalEntries: int, isComplete: bool}>
     */
    public function reportFor(TakeoutImport $import): array
    {
        $zipPaths = $this->tmpDirLocator->zipPathsFor($import);
        $extractedZipCount = $import->getExtractedZipCount();

        $report = [];
        foreach ($zipPaths as $index => $zipPath) {
            $totalEntries = $this->countEntries($zipPath);
            $isComplete = $index < $extractedZipCount;

            if ($isComplete) {
                $extractedEntries = $totalEntries;
            } elseif ($index === $extractedZipCount) {
                $extractedEntries = $this->readCheckpoint($import);
            } else {
                $extractedEntries = 0;
            }

            $report[] = [
                'name' => basename($zipPath),
                'extractedEntries' => $extractedEntries,
                'totalEntries' => $totalEntries,
                'isComplete' => $isComplete,
            ];
        }

        return $report;
    }

    private function countEntries(string $zipPath): int
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::RDONLY) !== true) {
            return 0;
        }

        $count = $zip->numFiles;
        $zip->close();

        return $count;
    }

    private function readCheckpoint(TakeoutImport $import): int
    {
        // TakeoutImportExtractHandler écrit dans sys_get_temp_dir(), PAS
        // dans le dossier des ZIP source (var/takeout-tmp/<uuid>/, géré par
        // TakeoutImportTmpDirLocator) — un seul .progress par import,
        // recréé pour chaque nouveau ZIP en cours (index dans CE ZIP).
        $progressPath = sys_get_temp_dir() . '/takeout-import-' . $import->getId()->toRfc4122() . '/.progress';
        if (!is_file($progressPath)) {
            return 0;
        }

        // .progress stocke le dernier INDEX extrait (base 0) — +1 pour le
        // nombre d'entrées effectivement extraites (même convention que
        // TakeoutZipExtractor).
        return (int) file_get_contents($progressPath) + 1;
    }
}
