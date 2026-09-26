<?php

declare(strict_types=1);

namespace App\Service\File;

use App\Entity\ContentFingerprint;
use App\Entity\File;
use App\Entity\User;
use App\Interface\Folder\DefaultFolderServiceInterface;
use App\Interface\File\FileUploadServiceInterface;
use App\Interface\File\StorageServiceInterface;
use App\Repository\ContentFingerprintRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Création de fichier depuis un upload web (session auth).
 * Symétrique à CreateFileService (upload API Platform) — cf. #440.
 *
 * Extrait de FileWebController::upload() : validation d'extension bloquée
 * et nettoyage de nom de fichier, jusqu'ici en dur dans le Controller.
 *
 * #327 — rejette aussi les doublons via fingerprint persistant (hash SHA-256
 * du contenu, scope par owner) : voir ContentFingerprint pour le détail des
 * garanties (survit à la suppression du File).
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
        private readonly ContentFingerprintRepository $contentFingerprintRepository,
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

        // Hashé avant store() : store() peut déplacer/renommer le fichier
        // (le tmp de $uploadedFile n'existe plus après un move_uploaded_file).
        $contentHash = hash_file('sha256', $uploadedFile->getPathname());
        if ($this->contentFingerprintRepository->existsForOwner($owner, $contentHash)) {
            throw new BadRequestHttpException('Ce fichier a déjà été importé.');
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

        $this->contentFingerprintRepository->save(new ContentFingerprint($owner, $contentHash));

        return $file;
    }
}
