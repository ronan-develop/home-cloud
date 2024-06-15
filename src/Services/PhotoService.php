<?php

namespace App\Services;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

// TODO : faire un système de miniatures pour les appercus et la consultation des photos
// TODO : faire la récupération de la date de création de la photo
class PhotoService
{
    private $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    public function add(UploadedFile $photo, ?string $directory = ''): string
    {
        $filename = md5(uniqid()) . '.' . $photo->guessExtension();

        // Verify that the file is an image
        if (!in_array($photo->guessClientExtension(), [
            'jpeg',
            'jpg',
            'png',
            'gif',
            'tiff',
            'bmp',
            'webp',
            'svg',
            'raw',
            'heic',
            'heif',
            'avif'
            ])) {

            throw new \InvalidArgumentException('Invalid file format. Only JPEG, PNG, and GIF images are allowed.');
        }

        // Create the destination directory if it doesn't exist
        $destinationDirectory = $this->params->get('photo_directory') . '/' . $directory;
        if (!file_exists($destinationDirectory)) {

            mkdir($destinationDirectory, 0755, true);
        }

        $photo->move($this->params->get('photo_directory') . '/' . $directory, $filename);

        return $filename;
    }

    public function remove(string $filename, ?string $directory = ''): bool
    {
        $path = $this->params->get('photo_directory') . '/' . $directory . '/' . $filename;

        if (file_exists($path)) {

            unlink($path);
            return true;
        }

        return false;
    }
}