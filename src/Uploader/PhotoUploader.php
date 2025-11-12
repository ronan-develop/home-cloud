<?php

namespace App\Uploader;

use App\Entity\Photo;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Photo\ExifExtractor;
use App\Photo\PhotoMimeTypeValidator;
use App\Form\Dto\PhotoUploadData;
use App\Exception\PhotoUploadException;
use App\Uploader\SafeFileMover;
use App\Uploader\UploadDirectoryManager;
use App\Interface\FileNameGeneratorInterface;


use App\Uploader\UploaderInterface;

class PhotoUploader implements UploaderInterface
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

    /**
     * UploaderInterface: détermine si ce service gère ce fichier (image)
     */
    public function supports(UploadedFile $file, array $context = []): bool
    {
        $mimeType = $file->getClientMimeType();
        // Utilise la liste des mimes autorisés du validateur
        return in_array($mimeType, $this->mimeTypeValidator->getAllowedMimeTypes(), true);
    }

    /**
     * UploaderInterface: upload générique (délègue à uploadPhoto)
     * $context doit contenir 'user' (User) et 'data' (PhotoUploadData)
     */
    public function upload(UploadedFile $file, array $context = []): Photo
    {
        if (!isset($context['user']) || !isset($context['data'])) {
            throw new \InvalidArgumentException('PhotoUploader nécessite user et data dans le contexte.');
        }
        $user = $context['user'];
        $data = $context['data'];
        $exifData = $context['exifData'] ?? [];
        return $this->uploadPhoto($file, $user, $data, $exifData);
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }
}
