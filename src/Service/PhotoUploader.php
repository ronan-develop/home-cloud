<?php

namespace App\Service;

use App\Entity\Photo;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;


use App\Service\ExifExtractor;
use App\Service\PhotoMimeTypeValidator;

class PhotoUploader
{
    private string $targetDirectory;
    private ExifExtractor $exifExtractor;
    private PhotoMimeTypeValidator $mimeTypeValidator;
    private UploadDirectoryManager $directoryManager;

    public function __construct(
        string $targetDirectory,
        ExifExtractor $exifExtractor,
        PhotoMimeTypeValidator $mimeTypeValidator,
        UploadDirectoryManager $directoryManager
    ) {
        $this->targetDirectory = $targetDirectory;
        $this->exifExtractor = $exifExtractor;
        $this->mimeTypeValidator = $mimeTypeValidator;
        $this->directoryManager = $directoryManager;
    }

    /**
     * Gère l'upload d'une photo et retourne une entité Photo complète
     * @param UploadedFile $file
     * @param User $user
     * @param array $data (title, description, isFavorite)
     * @param array $exifData (optionnel)
     * @return Photo
     */
    public function uploadPhoto(UploadedFile $file, User $user, array $data = [], array $exifData = []): Photo
    {
        $this->directoryManager->ensureDirectoryExists($this->targetDirectory);
        $mimeType = $file->getClientMimeType();
        $this->mimeTypeValidator->validate($file);
        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $originalName = $file->getClientOriginalName();
        $size = $file->getSize();

        $hash = hash_file('sha256', $file->getPathname());

        // Extraction EXIF via service dédié (SRP)
        $autoExif = $this->exifExtractor->extract($file);
        $file->move($this->targetDirectory, $filename);
        $finalExif = array_merge($autoExif, $exifData);

        $photo = new Photo();
        $photo->setFilename($filename)
            ->setOriginalName($originalName)
            ->setMimeType($mimeType)
            ->setSize($size)
            ->setHash($hash)
            ->setUploadedAt(new \DateTimeImmutable())
            ->setUser($user)
            ->setTitle($data['title'] ?? null)
            ->setDescription($data['description'] ?? null)
            ->setIsFavorite($data['isFavorite'] ?? null)
            ->setExifData($finalExif);

        return $photo;
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }
}
