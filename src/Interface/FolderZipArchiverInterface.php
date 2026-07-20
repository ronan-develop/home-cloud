<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Folder;

interface FolderZipArchiverInterface
{
    /**
     * Construit une archive zip du dossier (récursif, sous-dossiers inclus)
     * et retourne le chemin absolu du fichier temporaire généré.
     */
    public function archive(Folder $folder): string;
}
