<?php

declare(strict_types=1);

namespace App\Service\Takeout;

use App\Entity\TakeoutImport;
use App\Repository\TakeoutImportRepository;

/**
 * Abandonne un import Takeout pending : supprime le dossier temporaire
 * (var/takeout-tmp/<uuid>/) et la ligne en base (#481/#493).
 *
 * Réutilisé par TakeoutImportAbandonController (action explicite de
 * l'utilisateur) et TakeoutImportPurgeAbandonedCommand (purge automatique à
 * 7 jours) — même opération, deux déclencheurs différents.
 */
final class TakeoutImportAbandoner
{
    public function __construct(
        private readonly TakeoutImportRepository $repository,
        private readonly TakeoutImportTmpDirLocator $tmpDirLocator,
    ) {}

    public function abandon(TakeoutImport $import): void
    {
        $this->deleteTmpDir($import);
        $this->repository->remove($import);
    }

    private function deleteTmpDir(TakeoutImport $import): void
    {
        $importTmpDir = $this->tmpDirLocator->dirFor($import);
        if (!is_dir($importTmpDir)) {
            return;
        }

        array_map('unlink', glob($importTmpDir . '/*') ?: []);
        rmdir($importTmpDir);
    }
}
