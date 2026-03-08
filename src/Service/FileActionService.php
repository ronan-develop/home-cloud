<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Interface\AuthorizationCheckerInterface;
use App\Interface\FileRepositoryInterface;
use App\Interface\StorageServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * File-level operations: rename, move, delete.
 * Encapsulates file business logic separate from HTTP/REST concerns.
 *
 * Responsibility: File actions only (orchestrates lower-level services).
 *
 * Dependencies: All via interfaces (DIP compliant)
 * - StorageServiceInterface (disk I/O)
 * - AuthorizationCheckerInterface (access control)
 * - EntityManager (persistence)
 * - FileRepositoryInterface (data access)
 */
final class FileActionService
{
    public function __construct(
        private readonly FileRepositoryInterface $fileRepository,
        private readonly StorageServiceInterface $storageService,
        private readonly AuthorizationCheckerInterface $authChecker,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Rename a file with validation.
     *
     * @throws BadRequestHttpException if name invalid
     */
    public function rename(File $file, string $newName): void
    {
        // 1. Validate: length
        if (mb_strlen($newName) > 255) {
            throw new BadRequestHttpException('File name too long (255 max)');
        }

        // 2. Validate: characters (no slashes, colons, wildcards, quotes, etc.)
        if (!preg_match('/^[^\\\\\/:*?"<>|]+$/u', $newName)) {
            throw new BadRequestHttpException('Invalid characters in file name');
        }

        // 3. Persist
        $file->setOriginalName($newName);
        $this->em->flush();
    }

    /**
     * Move a file to another folder with security checks.
     *
     * @throws BadRequestHttpException if cycle or validation fails
     */
    public function move(File $file, Folder $targetFolder, User $requester): void
    {
        // 1. Auth: file owner
        $this->authChecker->assertOwns($file, $requester);

        // 2. Auth: target folder owner
        $this->authChecker->assertOwns($targetFolder, $requester);

        // 3. Validation: cycle check (prevent B > A > C, move B under C)
        if ($this->authChecker->wouldCreateCycle($file->getFolder(), $targetFolder)) {
            throw new BadRequestHttpException('Moving would create a folder cycle');
        }

        // 4. Persist
        $file->setFolder($targetFolder);
        $this->em->flush();
    }

    /**
     * Delete a file (disk + metadata).
     *
     * Orchestrates:
     * - StorageService: disk cleanup
     * - EntityManager: entity removal
     *
     * Note: Media/thumbnail cleanup is handled by Doctrine CASCADE rules or
     * needs to be added if manual cleanup is required.
     */
    public function delete(File $file): void
    {
        // 1. Cleanup file from disk (graceful: log but don't fail if file missing)
        try {
            $this->storageService->delete($file->getPath());
        } catch (\Exception $e) {
            // Log: disk file missing is not critical
            // TODO: Add logger to log this
        }

        // 2. Remove entity (cascade rules will handle Media cleanup if configured)
        $this->em->remove($file);
        $this->em->flush();
    }
}
