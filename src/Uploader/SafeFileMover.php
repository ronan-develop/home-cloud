<?php

namespace App\Uploader;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Exception\PhotoUploadException;

class SafeFileMover
{
    public function move(UploadedFile $file, string $targetDir, string $filename): void
    {
        try {
            $file->move($targetDir, $filename);
        } catch (\Throwable $e) {
            throw PhotoUploadException::forMove($filename);
        }
    }
}
