<?php

namespace App\Uploader;

use App\Entity\User;
use App\Uploader\FileUploader;
use App\Uploader\FileManager;
use App\Uploader\FileUploadValidator;
use App\Uploader\UploadLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploadService
{
    public function __construct(
        private FileUploadValidator $fileUploadValidator,
        private FileUploader $fileUploader,
        private FileManager $fileManager,
        private UploadLogger $uploadLogger
    ) {}

    /**
     * @throws \DomainException
     */
    public function handle(UploadedFile $uploadedFile, ?User $user): void
    {
        if (!$user instanceof User) {
            throw new \DomainException('L’utilisateur courant n’est pas une entité User.');
        }
        $this->fileUploadValidator->validate($uploadedFile);
        $this->uploadLogger->logValidation($uploadedFile, $user, 'Validation réussie');
        $result = $this->fileUploader->upload($uploadedFile);
        $this->fileManager->createAndSave($result, $user);
        $this->uploadLogger->logSuccess($uploadedFile, $user);
    }
}
