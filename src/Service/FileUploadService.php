<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\File;
use App\Entity\User;
use App\Interface\DefaultFolderServiceInterface;
use App\Interface\FileUploadServiceInterface;
use App\Interface\StorageServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Création de fichier depuis un upload web (session auth).
 * Symétrique à CreateFileService (upload API Platform) — cf. #440.
 *
 * Extrait de FileWebController::upload() : validation d'extension bloquée
 * et nettoyage de nom de fichier, jusqu'ici en dur dans le Controller.
 */
final class FileUploadService implements FileUploadServiceInterface
{
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
        'exe', 'msi', 'com', 'bat', 'cmd', 'ps1', 'psm1', 'psd1', 'scr', 'pif',
        'vbs', 'vbe', 'wsf', 'wsh', 'gadget', 'msc', 'msp', 'mst',
        'run', 'elf', 'appimage', 'deb', 'rpm',
        'dmg', 'pkg', 'app',
        'jar', 'jnlp',
        'asp', 'aspx', 'jsp', 'cfm',
    ];

    public function __construct(
        private readonly StorageServiceInterface $storageService,
        private readonly DefaultFolderServiceInterface $defaultFolderService,
        private readonly EntityManagerInterface $em,
    ) {}

    public function createFromUpload(
        UploadedFile $uploadedFile,
        User $owner,
        ?string $folderId = null,
    ): File {
        $ext = strtolower($uploadedFile->getClientOriginalExtension() ?? '');
        if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            throw new BadRequestHttpException(
                sprintf('File type ".%s" is not allowed.', $ext)
            );
        }

        $folder = $this->defaultFolderService->resolve($folderId, null, $owner);

        // Retire les caractères de contrôle ET < > (neutralise le XSS stocké si ce nom
        // est un jour affiché sans échappement côté client — défense en profondeur, cf.
        // FilenameValidator qui rejette ces mêmes caractères sur les autres chemins).
        $originalName = preg_replace('/[\x00-\x1F\x7F<>]/u', '', $uploadedFile->getClientOriginalName());
        $mimeType = $uploadedFile->getClientMimeType();
        $size = $uploadedFile->getSize(); // Avant store() qui déplace le fichier

        ['path' => $path, 'neutralized' => $neutralized] = $this->storageService->store($uploadedFile);

        $file = new File(
            $originalName,
            $mimeType,
            $size,
            $path,
            $folder,
            $owner,
            $neutralized,
        );

        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }
}
