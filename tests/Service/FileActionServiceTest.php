<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Interface\Auth\AuthorizationCheckerInterface;
use App\Interface\File\FileRepositoryInterface;
use App\Interface\File\StorageServiceInterface;
use App\Service\File\FileActionService;
use App\Service\File\FilenameValidator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FileActionServiceTest extends TestCase
{
    private FileActionService $service;
    private StorageServiceInterface $storageService;
    private AuthorizationCheckerInterface $authChecker;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    private FileRepositoryInterface $fileRepository;

    protected function setUp(): void
    {
        // Stubs par défaut (aucune vérification d'appel) — chaque test qui a
        // besoin de vérifier un appel précis (expects()) réassigne la
        // propriété concernée avec un createMock() local puis rebuild() (#454).
        $this->storageService = $this->createStub(StorageServiceInterface::class);
        $this->authChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->fileRepository = $this->createStub(FileRepositoryInterface::class);

        $this->rebuild();
    }

    private function rebuild(): void
    {
        $this->service = new FileActionService(
            $this->fileRepository,
            $this->storageService,
            $this->authChecker,
            $this->em,
            new FilenameValidator(),
            $this->logger,
        );
    }

    public function testRenameValidatesNameLength(): void
    {
        // Setup
        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('Root', $owner);
        $file = new File('old.txt', 'text/plain', 100, '/path/old.txt', $folder, $owner);
        $longName = str_repeat('a', 256); // 256 chars (exceeds 255 limit)

        // Assert
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('too long');

        // Act
        $this->service->rename($file, $longName);
    }

    public function testRenameValidatesInvalidCharacters(): void
    {
        // Setup
        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('Root', $owner);
        $file = new File('old.txt', 'text/plain', 100, '/path/old.txt', $folder, $owner);
        $invalidName = 'file/name:with?invalid*chars';

        // Assert
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid characters');

        // Act
        $this->service->rename($file, $invalidName);
    }

    public function testRenameSucceedsWithValidName(): void
    {
        // Setup
        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('Root', $owner);
        $file = new File('old.txt', 'text/plain', 100, '/path/old.txt', $folder, $owner);

        // Expect flush to be called
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())
            ->method('flush');
        $this->rebuild();

        // Act
        $this->service->rename($file, 'new.txt');

        // Assert: file name changed
        $this->assertEquals('new.txt', $file->getOriginalName());
    }

    public function testMoveChecksFileOwnership(): void
    {
        // Setup
        $owner1 = new User('owner1@example.com', 'Owner 1');
        $owner2 = new User('owner2@example.com', 'Owner 2');
        $folder1 = new Folder('Folder1', $owner1);
        $folder2 = new Folder('Folder2', $owner2);
        $file = new File('test.txt', 'text/plain', 100, '/path/test.txt', $folder1, $owner1);

        // Expect authChecker to reject file ownership (first call)
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->authChecker->expects($this->once())
            ->method('assertOwns')
            ->with($file, $owner2)
            ->willThrowException(new AccessDeniedHttpException('do not own'));
        $this->rebuild();

        // Assert
        $this->expectException(AccessDeniedHttpException::class);

        // Act
        $this->service->move($file, $folder2, $owner2);
    }

    public function testMoveChecksFolderOwnership(): void
    {
        // Setup
        $owner = new User('owner@example.com', 'Owner');
        $otherOwner = new User('other@example.com', 'Other');
        $source = new Folder('Source', $owner);
        $target = new Folder('Target', $otherOwner);
        $file = new File('test.txt', 'text/plain', 100, '/path/test.txt', $source, $owner);

        // Setup authChecker: pass file check, fail folder check
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->authChecker->expects($this->exactly(2))
            ->method('assertOwns')
            ->willReturnOnConsecutiveCalls(
                null, // First call (file) passes
                $this->throwException(new AccessDeniedHttpException('do not own')) // Second call (folder) fails
            );
        $this->rebuild();

        // Assert
        $this->expectException(AccessDeniedHttpException::class);

        // Act
        $this->service->move($file, $target, $owner);
    }

    public function testMoveDetectsCycle(): void
    {
        // Setup
        $owner = new User('owner@example.com', 'Owner');
        $a = new Folder('A', $owner);
        $b = new Folder('B', $owner, parent: $a);
        $c = new Folder('C', $owner, parent: $b);
        $file = new File('test.txt', 'text/plain', 100, '/path/test.txt', $a, $owner);

        // authChecker passes ownership checks
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->authChecker->expects($this->exactly(2))
            ->method('assertOwns');

        // authChecker detects cycle: moving A under C would create A > B > C > A
        $this->authChecker->expects($this->once())
            ->method('wouldCreateCycle')
            ->with($a, $c)
            ->willReturn(true);
        $this->rebuild();

        // Assert
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('cycle');

        // Act
        $this->service->move($file, $c, $owner);
    }

    public function testMoveSucceedsWithValidTarget(): void
    {
        // Setup
        $owner = new User('owner@example.com', 'Owner');
        $source = new Folder('Source', $owner);
        $target = new Folder('Target', $owner);
        $file = new File('test.txt', 'text/plain', 100, '/path/test.txt', $source, $owner);

        // authChecker passes all checks
        $this->authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->authChecker->expects($this->exactly(2))
            ->method('assertOwns');
        $this->authChecker->expects($this->once())
            ->method('wouldCreateCycle')
            ->willReturn(false);

        // Expect flush
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())
            ->method('flush');
        $this->rebuild();

        // Act
        $this->service->move($file, $target, $owner);

        // Assert: file folder changed
        $this->assertEquals($target, $file->getFolder());
    }

    public function testDeleteRemovesThumbnailAndFile(): void
    {
        // Setup
        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('Root', $owner);
        $file = new File('test.jpg', 'image/jpeg', 5000, '/path/test.jpg', $folder, $owner);

        // storageService deletes file
        $this->storageService = $this->createMock(StorageServiceInterface::class);
        $this->storageService->expects($this->once())
            ->method('delete')
            ->with('/path/test.jpg');

        // em removes entity and flushes
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())
            ->method('remove')
            ->with($file);
        $this->em->expects($this->once())
            ->method('flush');

        // Happy path : rien à signaler, pas de log
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->never())->method('warning');
        $this->rebuild();

        // Act
        $this->service->delete($file);

        // Assert (mocks verify expectations)
    }

    public function testDeleteHandlesStorageError(): void
    {
        // Setup
        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('Root', $owner);
        $file = new File('test.jpg', 'image/jpeg', 5000, '/path/test.jpg', $folder, $owner);

        // storageService throws but we continue
        $this->storageService = $this->createMock(StorageServiceInterface::class);
        $this->storageService->expects($this->once())
            ->method('delete')
            ->willThrowException(new \Exception('File not found'));

        // em still removes entity (graceful)
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())
            ->method('remove')
            ->with($file);
        $this->em->expects($this->once())
            ->method('flush');

        // L'échec de suppression disque doit être loggé (pas juste avalé silencieusement)
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('test.jpg'),
                $this->callback(fn (array $context) => ($context['path'] ?? null) === '/path/test.jpg'),
            );
        $this->rebuild();

        // Act & Assert (no exception thrown, graceful)
        $this->service->delete($file);
    }
}

