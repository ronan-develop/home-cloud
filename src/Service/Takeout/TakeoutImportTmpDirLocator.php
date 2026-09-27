<?php

declare(strict_types=1);

namespace App\Service\Takeout;

use App\Entity\TakeoutImport;

/**
 * Localise le dossier temporaire d'un import Takeout et ses ZIP déjà reçus
 * (var/takeout-tmp/<uuid>/*.zip) — logique auparavant dupliquée dans
 * TakeoutImportFilesStatusController, TakeoutImportStartController et
 * TakeoutImportListPendingController.
 */
final class TakeoutImportTmpDirLocator
{
    public function __construct(
        private readonly string $takeoutTmpDir,
    ) {}

    public function dirFor(TakeoutImport $import): string
    {
        return sprintf('%s/%s', $this->takeoutTmpDir, $import->getId()->toRfc4122());
    }

    /**
     * @return list<string>
     */
    public function zipPathsFor(TakeoutImport $import): array
    {
        return glob($this->dirFor($import) . '/*.zip') ?: [];
    }
}
