<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Folder;
use App\Entity\User;
use App\Interface\Folder\DefaultFolderServiceInterface;
use App\Interface\File\StorageServiceInterface;
use App\Repository\ContentFingerprintRepository;
use App\Repository\UserRepository;
use App\Security\GuestRestrictionChecker;
use App\Service\File\CreateFileService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CreateFileServiceTest extends TestCase
{
    private CreateFileService $service;
    private StorageServiceInterface $storageService;
    private DefaultFolderServiceInterface $defaultFolderService;
    private UserRepository $userRepository;
    private EntityManagerInterface $em;
    private ContentFingerprintRepository $contentFingerprintRepository;

    protected function setUp(): void
    {
        // Stubs par défaut (aucune vérification d'appel) — chaque test qui a
        // besoin de vérifier un appel précis (expects()) réassigne la
        // propriété concernée avec un createMock() local puis rebuild() (#454).
        $this->storageService = $this->createStub(StorageServiceInterface::class);
        $this->defaultFolderService = $this->createStub(DefaultFolderServiceInterface::class);
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->contentFingerprintRepository = $this->createStub(ContentFingerprintRepository::class);
        $this->contentFingerprintRepository->method('existsForOwner')->willReturn(false);

        $this->rebuild();
    }

    private function rebuild(): void
    {
        $this->service = new CreateFileService(
            $this->storageService,
            $this->defaultFolderService,
            $this->em,
            $this->userRepository,
            new GuestRestrictionChecker(),
            $this->contentFingerprintRepository,
        );
    }

    public function testCreateFromUploadValidatesExecutableByMime(): void
    {
        // Setup: file with blocked MIME, harmless extension
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'MZ');  // PE executable header
        $uploadedFile = new UploadedFile(
            $tmpFile,
            'archive.zip',  // Harmless extension
            'application/x-msdownload',  // Executable MIME
            null,
            true
        );

        $owner = new User('owner@example.com', 'Owner');
        $ownerId = (string)$owner->getId();

        // Setup mocks: user lookup is NOT reached (validation happens first)
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userRepository->expects($this->never())
            ->method('find');
        $this->rebuild();

        // Assert: MIME check is first line of defense
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Executable files not allowed');

        // Act
        $this->service->createFromUpload($uploadedFile, $ownerId);
    }

    public function testCreateFromUploadValidatesBlockedExtension(): void
    {
        // Setup: .sh file (blocked by extension even with harmless MIME)
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, '#!/bin/bash');
        $uploadedFile = new UploadedFile(
            $tmpFile,
            'script.sh',
            'text/plain',  // MIME is harmless, but extension is blocked
            null,
            true
        );

        $owner = new User('owner@example.com', 'Owner');
        $ownerId = (string)$owner->getId();

        // Assert: blocked extension check happens after MIME check
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('not allowed');

        // Act
        $this->service->createFromUpload($uploadedFile, $ownerId);
    }

    public function testCreateFromUploadSanitizesFileName(): void
    {
        // Setup: filename with null bytes + control chars + < > (XSS stocké, F9 de
        // l'audit sécurité — un nom affiché sans échappement côté client exécuterait
        // une balise <img onerror=...> injectée via le nom de fichier uploadé).
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'content');
        $uploadedFile = new UploadedFile(
            $tmpFile,
            "file\x00with\x1Fcontrol<img onerror=alert(1)>.txt",
            'text/plain',
            null,
            true
        );

        $owner = new User('owner@example.com', 'Owner');
        $ownerId = (string)$owner->getId();
        $folder = new Folder('Root', $owner);

        // Setup mocks: user exists
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userRepository->expects($this->once())
            ->method('find')
            ->with($ownerId)
            ->willReturn($owner);

        // Default folder resolution
        $this->defaultFolderService = $this->createMock(DefaultFolderServiceInterface::class);
        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->willReturn($folder);

        // Storage succeeds
        $this->storageService = $this->createMock(StorageServiceInterface::class);
        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn(['path' => '/path/file.txt', 'neutralized' => false]);

        // Persistence
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->rebuild();

        // Act
        $file = $this->service->createFromUpload($uploadedFile, $ownerId);

        // Assert: sanitized
        $this->assertStringNotContainsString("\x00", $file->getOriginalName());
        $this->assertStringNotContainsString("\x1F", $file->getOriginalName());
        $this->assertStringNotContainsString('<', $file->getOriginalName());
        $this->assertStringNotContainsString('>', $file->getOriginalName());
    }

    public function testCreateFromUploadWithFolderId(): void
    {
        // Setup
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'content');
        $uploadedFile = new UploadedFile(
            $tmpFile,
            'document.pdf',
            'application/pdf',
            null,
            true
        );

        $owner = new User('owner@example.com', 'Owner');
        $ownerId = (string)$owner->getId();
        $targetFolder = new Folder('Documents', $owner);
        $folderId = (string)$targetFolder->getId();

        // Setup mocks
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userRepository->expects($this->once())
            ->method('find')
            ->willReturn($owner);

        $this->defaultFolderService = $this->createMock(DefaultFolderServiceInterface::class);
        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->with($folderId, null, $owner, null)
            ->willReturn($targetFolder);

        $this->storageService = $this->createMock(StorageServiceInterface::class);
        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn(['path' => '/path/document.pdf', 'neutralized' => false]);

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->rebuild();

        // Act
        $file = $this->service->createFromUpload($uploadedFile, $ownerId, $folderId);

        // Assert
        $this->assertEquals($targetFolder, $file->getFolder());
    }

    public function testCreateFromUploadWithNewFolderName(): void
    {
        // Setup
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'content');
        $uploadedFile = new UploadedFile(
            $tmpFile,
            'image.jpg',
            'image/jpeg',
            null,
            true
        );

        $owner = new User('owner@example.com', 'Owner');
        $ownerId = (string)$owner->getId();
        $newFolder = new Folder('New Folder', $owner);

        // Setup mocks
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userRepository->expects($this->once())
            ->method('find')
            ->willReturn($owner);

        $this->defaultFolderService = $this->createMock(DefaultFolderServiceInterface::class);
        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->with(null, 'New Folder', $owner, null)
            ->willReturn($newFolder);

        $this->storageService = $this->createMock(StorageServiceInterface::class);
        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn(['path' => '/path/image.jpg', 'neutralized' => false]);

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->rebuild();

        // Act
        $file = $this->service->createFromUpload(
            $uploadedFile,
            $ownerId,
            newFolderName: 'New Folder'
        );

        // Assert
        $this->assertEquals($newFolder, $file->getFolder());
    }

    public function testCreateFromUploadWithRelativePathPassesItToResolve(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'content');
        $uploadedFile = new UploadedFile($tmpFile, 'scan.pdf', 'application/pdf', null, true);

        $owner = new User('owner@example.com', 'Owner');
        $ownerId = (string) $owner->getId();
        $targetFolder = new Folder('Archives', $owner);
        $folderId = (string) $targetFolder->getId();
        $subFolder = new Folder('2026-07-10-BMA', $owner, $targetFolder);

        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userRepository->expects($this->once())
            ->method('find')
            ->willReturn($owner);

        $this->defaultFolderService = $this->createMock(DefaultFolderServiceInterface::class);
        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->with($folderId, null, $owner, '2026-07-10-BMA')
            ->willReturn($subFolder);

        $this->storageService = $this->createMock(StorageServiceInterface::class);
        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn(['path' => '/path/scan.pdf', 'neutralized' => false]);

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->rebuild();

        $file = $this->service->createFromUpload(
            $uploadedFile,
            $ownerId,
            $folderId,
            relativePath: '2026-07-10-BMA',
        );

        $this->assertSame($subFolder, $file->getFolder());
    }

    public function testCreateFromUploadPersistsFile(): void
    {
        // Setup
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'file content');
        $uploadedFile = new UploadedFile(
            $tmpFile,
            'data.txt',
            'text/plain',
            null,
            true
        );

        $owner = new User('owner@example.com', 'Owner');
        $ownerId = (string)$owner->getId();
        $folder = new Folder('Root', $owner);

        // Setup mocks
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userRepository->expects($this->once())
            ->method('find')
            ->willReturn($owner);

        $this->defaultFolderService = $this->createMock(DefaultFolderServiceInterface::class);
        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->willReturn($folder);

        $this->storageService = $this->createMock(StorageServiceInterface::class);
        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn([
                'path' => '/2026/03/uuid.txt',
                'neutralized' => false,
            ]);

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->rebuild();

        // Act
        $file = $this->service->createFromUpload($uploadedFile, $ownerId);

        // Assert
        $this->assertEquals('data.txt', $file->getOriginalName());
        $this->assertEquals('text/plain', $file->getMimeType());
        $this->assertEquals('/2026/03/uuid.txt', $file->getPath());
        $this->assertFalse($file->isNeutralized());
        $this->assertEquals($owner, $file->getOwner());
    }

    public function testCreateFromUploadMarksNeutralizedFile(): void
    {
        // Setup: .svg file (neutralized by StorageService)
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, '<svg>...</svg>');
        $uploadedFile = new UploadedFile(
            $tmpFile,
            'image.svg',
            'image/svg+xml',
            null,
            true
        );

        $owner = new User('owner@example.com', 'Owner');
        $ownerId = (string)$owner->getId();
        $folder = new Folder('Root', $owner);

        // Setup mocks
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userRepository->expects($this->once())
            ->method('find')
            ->willReturn($owner);

        $this->defaultFolderService = $this->createMock(DefaultFolderServiceInterface::class);
        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->willReturn($folder);

        $this->storageService = $this->createMock(StorageServiceInterface::class);
        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn([
                'path' => '/2026/03/uuid.bin',
                'neutralized' => true,  // File was neutralized
            ]);

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->rebuild();

        // Act
        $file = $this->service->createFromUpload($uploadedFile, $ownerId);

        // Assert
        $this->assertTrue($file->isNeutralized());
    }

    public function testCreateFromUploadThrowsForGuestAccount(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'contenu');
        $uploadedFile = new UploadedFile($tmpFile, 'doc.txt', 'text/plain', null, true);

        $guest = new User('guest@example.com', 'Guest');
        $guest->markAsGuest();

        $this->userRepository->method('find')->willReturn($guest);

        $this->expectException(\App\Exception\GuestNotAllowedException::class);
        $this->service->createFromUpload($uploadedFile, (string) $guest->getId());
    }

    public function testCreateFromUploadRejectsDuplicateContentForSameOwner(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'contenu déjà importé');
        $uploadedFile = new UploadedFile($tmpFile, 'doublon.txt', 'text/plain', null, true);

        $owner = new User('owner@example.com', 'Owner');
        $ownerId = (string) $owner->getId();

        $this->userRepository->method('find')->willReturn($owner);

        $this->contentFingerprintRepository = $this->createMock(ContentFingerprintRepository::class);
        $this->contentFingerprintRepository->expects($this->once())
            ->method('existsForOwner')
            ->with($owner, hash_file('sha256', $tmpFile))
            ->willReturn(true);

        $this->storageService = $this->createMock(StorageServiceInterface::class);
        $this->storageService->expects($this->never())->method('store');
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->em->expects($this->never())->method('persist');
        $this->rebuild();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Ce fichier a déjà été importé.');

        $this->service->createFromUpload($uploadedFile, $ownerId);
    }

    public function testCreateFromUploadRecordsFingerprintAfterSuccessfulUpload(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'contenu nouveau');
        $uploadedFile = new UploadedFile($tmpFile, 'nouveau.txt', 'text/plain', null, true);

        $owner = new User('owner@example.com', 'Owner');
        $ownerId = (string) $owner->getId();
        $folder = new Folder('Root', $owner);

        $this->userRepository->method('find')->willReturn($owner);
        $this->defaultFolderService->method('resolve')->willReturn($folder);
        $this->storageService->method('store')->willReturn(['path' => '/path/nouveau.txt', 'neutralized' => false]);

        $expectedHash = hash_file('sha256', $tmpFile);

        $this->contentFingerprintRepository = $this->createMock(ContentFingerprintRepository::class);
        $this->contentFingerprintRepository->method('existsForOwner')->willReturn(false);
        $this->contentFingerprintRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (\App\Entity\ContentFingerprint $fingerprint) use ($owner, $expectedHash): bool {
                return $fingerprint->getOwner() === $owner
                    && $fingerprint->getContentHash() === $expectedHash;
            }));
        $this->rebuild();

        $this->service->createFromUpload($uploadedFile, $ownerId);
    }
}
