<?php

namespace App\Service;

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
        $this->logger->info('Upload réussi', [
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mimeType' => $file->getMimeType(),
            'user' => $user->getUserIdentifier(),
        ]);
    }

    public function logError(UploadedFile $file, User $user, string $error): void
    {
        $this->logger->error('Erreur upload', [
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mimeType' => $file->getMimeType(),
            'user' => $user->getUserIdentifier(),
            'error' => $error,
        ]);
    }

    public function logValidation(UploadedFile $file, User $user, string $validation): void
    {
        $this->logger->debug('Validation upload', [
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mimeType' => $file->getMimeType(),
            'user' => $user->getUserIdentifier(),
            'validation' => $validation,
        ]);
    }
}
