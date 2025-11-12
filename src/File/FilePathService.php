<?php

namespace App\File;

use App\Entity\File;
use App\Security\FilePathSecurity;

class FilePathService
{
    /**
     * Tente de sécuriser le chemin d'un fichier, retourne null en cas d'erreur
     * @param FilePathSecurity $filePathSecurity
     * @param File $file
     * @return string|null
     */
    public function getSafePathOrNull(FilePathSecurity $filePathSecurity, File $file): ?string
    {
        try {
            return $filePathSecurity->assertSafePath($file->getPath());
        } catch (\RuntimeException $e) {
            return null;
        }
    }
}
