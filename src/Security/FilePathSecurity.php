<?php

namespace App\Security;

class FilePathSecurity
{
    private string $uploadDir;

    public function __construct(string $uploadDir)
    {
        $this->uploadDir = realpath($uploadDir);
    }

    public function assertSafePath(string $path): string
    {
        $realPath = realpath($path);
        if (!$realPath || strpos($realPath, $this->uploadDir) !== 0) {
            throw new \RuntimeException('Chemin de fichier non autorisé.');
        }
        return $realPath;
    }

    public function deleteFile(string $path): void
    {
        $realPath = $this->assertSafePath($path);
        if (file_exists($realPath)) {
            @unlink($realPath);
        }
    }
}
