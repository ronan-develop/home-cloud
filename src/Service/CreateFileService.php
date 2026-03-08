<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\File;
use App\Entity\User;
use App\Interface\DefaultFolderServiceInterface;
use App\Interface\StorageServiceInterface;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Orchestrates file upload workflow.
 * Separates upload logic from HTTP controller.
 *
 * Workflow: validate → store → persist → dispatch async
 *
 * Dependencies: All via interfaces (DIP compliant)
 * - StorageServiceInterface (disk I/O)
 * - DefaultFolderServiceInterface (folder resolution)
 * - EntityManager (persistence)
 * - UserRepository (user lookup)
 */
final class CreateFileService
{
    // Blocked MIME types (executables, scripts)
    private const BLOCKED_MIMES = [
        'application/x-msdownload',
        'application/x-msdos-program',
        'application/x-executable',
        'application/x-elf',
    ];

    // Blocked extensions (defense in depth)
    private const BLOCKED_EXTENSIONS = [
        'exe', 'sh', 'bat', 'ps1', 'py', 'rb', 'pl', 'bash',
        'msi', 'cmd', 'jar', 'asp', 'aspx', 'jsp', 'phar', 'dmg', 'deb', 'rpm', 'apk'
    ];

    public function __construct(
        private readonly StorageServiceInterface $storageService,
        private readonly DefaultFolderServiceInterface $defaultFolderService,
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
    ) {}

    /**
     * Create and store a file from upload.
     *
     * @param UploadedFile $uploadedFile  HTTP file from request
     * @param string $ownerId              User UUID
     * @param string|null $folderId        Target folder (optional)
     * @param string|null $newFolderName   Create new folder (optional)
     * @return File                        Persisted entity
     *
     * @throws BadRequestHttpException     if validation fails
     * @throws NotFoundHttpException       if user/folder not found
     */
    public function createFromUpload(
        UploadedFile $uploadedFile,
        string $ownerId,
        ?string $folderId = null,
        ?string $newFolderName = null,
    ): File {
        // 1. Security FIRST: validate file is not executable (before any DB lookup)
        $this->validateExecutable($uploadedFile);

        // 2. Auth: fetch user
        $owner = $this->userRepository->find($ownerId)
            ?? throw new NotFoundHttpException('User not found');

        // 3. Folder resolution: folderId > newFolderName > Uploads
        $folder = $this->defaultFolderService->resolve($folderId, $newFolderName, $owner);

        // 4. Extract metadata (name, mime, size)
        $metadata = $this->extractMetadata($uploadedFile);

        // 5. Store physically (disk I/O)
        $storeResult = $this->storageService->store($uploadedFile);

        // 6. Create entity
        $file = new File(
            originalName: $metadata['name'],
            mimeType: $metadata['mimeType'],
            size: $metadata['size'],
            path: $storeResult['path'],
            folder: $folder,
            owner: $owner,
            neutralized: $storeResult['neutralized'],
        );

        // 7. Persist
        $this->em->persist($file);
        $this->em->flush();

        // 8. Dispatch async: media processing (image EXIF, thumbnail, etc.)
        // TODO: Implement media dispatcher when needed

        return $file;
    }

    /**
     * Validate file is not executable.
     *
     * @throws BadRequestHttpException
     */
    private function validateExecutable(UploadedFile $file): void
    {
        // Check client-declared MIME type (user's browser claim, NOT disk inspection)
        $clientMime = $file->getClientMimeType() ?? 'application/octet-stream';
        if (in_array($clientMime, self::BLOCKED_MIMES, true)) {
            throw new BadRequestHttpException('Executable files not allowed');
        }

        // Check extension (defense in depth)
        $ext = strtolower($file->getClientOriginalExtension() ?? '');
        if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            throw new BadRequestHttpException(
                sprintf('File extension ".%s" not allowed', $ext)
            );
        }
    }

    /**
     * Extract metadata from uploaded file.
     * Sanitize original filename (remove null bytes, control chars).
     *
     * @return array{name: string, mimeType: string, size: int}
     */
    private function extractMetadata(UploadedFile $file): array
    {
        $originalName = $file->getClientOriginalName() ?? 'unnamed';

        // Sanitize: remove null bytes, control characters
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'unnamed';

        return [
            'name' => $sanitized,
            'mimeType' => $file->getClientMimeType() ?? $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize() ?: 0,
        ];
    }
}
