<?php

namespace App\Service;

use App\Entity\Photo;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;


use App\Service\ExifExtractor;
use App\Service\PhotoMimeTypeValidator;
use App\Form\Dto\PhotoUploadData;
use App\Exception\PhotoUploadException;
use App\Service\SafeFileMover;

use App\Service\FileNameGeneratorInterface;

class PhotoUploader
{
    public function __construct(
        private readonly string $targetDirectory,
        private readonly ExifExtractor $exifExtractor,
        private readonly PhotoMimeTypeValidator $mimeTypeValidator,
        private readonly UploadDirectoryManager $directoryManager,
        private readonly SafeFileMover $fileMover,
        private readonly FileNameGeneratorInterface $fileNameGenerator
    ) {}

    /**
     * Gère l'upload d'une photo et retourne une entité Photo complète
     * @param UploadedFile $file
     * @param User $user
     * @param PhotoUploadData $data
     * @param array $exifData (optionnel)
     * @return Photo
     */
    public function uploadPhoto(UploadedFile $file, User $user, PhotoUploadData $data, array $exifData = []): Photo
    {
        $this->directoryManager->ensureDirectoryExists($this->targetDirectory);
        $mimeType = $file->getClientMimeType();
        $this->mimeTypeValidator->validate($file);
        $filename = $this->fileNameGenerator->generate($file->getClientOriginalName());
        $originalName = $file->getClientOriginalName();
        $size = $file->getSize();

        $hash = hash_file('sha256', $file->getPathname());

        // Extraction EXIF via service dédié (SRP)
        $autoExif = $this->exifExtractor->extract($file);
        $this->fileMover->move($file, $this->targetDirectory, $filename);
        $finalExif = array_merge($autoExif, $exifData);

        $photo = new Photo();
        $photo->setFilename($filename)
            ->setOriginalName($originalName)
            ->setMimeType($mimeType)
            ->setSize($size)
            ->setHash($hash)
            ->setUploadedAt(new \DateTimeImmutable())
            ->setUser($user)
            ->setTitle($data->title)
            ->setDescription($data->description)
            ->setIsFavorite($data->isFavorite)
            ->setExifData($finalExif);

        return $photo;
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }
}
