<?php

namespace App\Service;

use App\Exception\PhotoUploadException;

class SafeDirectoryManager
{
    public function ensureDirectoryExists(string $directory): void
    {
        try {
            if (!is_dir($directory)) {
                if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new \RuntimeException("Impossible de créer le répertoire d'upload : $directory");
                }
            }
            if (!is_writable($directory)) {
                throw new \RuntimeException("Le répertoire d'upload n'est pas accessible en écriture : $directory");
            }
        } catch (\Throwable $e) {
            throw PhotoUploadException::forDirectory($e->getMessage(), $directory);
        }
    }
}
