<?php

namespace App\Uploader;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Entity\User;

class UploadLogger
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function logSuccess(UploadedFile $file, User $user): void
    {
        $size = @file_exists($file->getPathname()) ? $file->getSize() : null;
        $mimeType = @file_exists($file->getPathname()) ? $file->getMimeType() : null;
        $this->logger->info('Upload réussi', [
            'filename' => $file->getClientOriginalName(),
            'size' => $size,
            'mimeType' => $mimeType,
            'user' => $user->getUserIdentifier(),
        ]);
    }

    public function logError(UploadedFile $file, User $user, string $error): void
    {
        $size = @file_exists($file->getPathname()) ? $file->getSize() : null;
        $mimeType = @file_exists($file->getPathname()) ? $file->getMimeType() : null;
        $this->logger->error('Erreur upload', [
            'filename' => $file->getClientOriginalName(),
            'size' => $size,
            'mimeType' => $mimeType,
            'user' => $user->getUserIdentifier(),
            'error' => $error,
        ]);
    }

    public function logValidation(UploadedFile $file, User $user, string $validation): void
    {
        $size = @file_exists($file->getPathname()) ? $file->getSize() : null;
        $mimeType = @file_exists($file->getPathname()) ? $file->getMimeType() : null;
        $this->logger->debug('Validation upload', [
            'filename' => $file->getClientOriginalName(),
            'size' => $size,
            'mimeType' => $mimeType,
            'user' => $user->getUserIdentifier(),
            'validation' => $validation,
        ]);
    }
}
