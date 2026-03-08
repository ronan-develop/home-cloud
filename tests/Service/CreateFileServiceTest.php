<?php
declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Folder;
use App\Entity\User;
use App\Interface\DefaultFolderServiceInterface;
use App\Interface\StorageServiceInterface;
use App\Repository\UserRepository;
use App\Service\CreateFileService;
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

    protected function setUp(): void
    {
        // Create mocks for interfaces (DIP)
        $this->storageService = $this->createMock(StorageServiceInterface::class);
        $this->defaultFolderService = $this->createMock(DefaultFolderServiceInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->service = new CreateFileService(
            $this->storageService,
            $this->defaultFolderService,
            $this->em,
            $this->userRepository,
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
        $this->userRepository->expects($this->never())
            ->method('find');

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
        // Setup: filename with null bytes + control chars
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'content');
        $uploadedFile = new UploadedFile(
            $tmpFile,
            "file\x00with\x1Fcontrol.txt",
            'text/plain',
            null,
            true
        );

        $owner = new User('owner@example.com', 'Owner');
        $ownerId = (string)$owner->getId();
        $folder = new Folder('Root', $owner);

        // Setup mocks: user exists
        $this->userRepository->expects($this->once())
            ->method('find')
            ->with($ownerId)
            ->willReturn($owner);

        // Default folder resolution
        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->willReturn($folder);

        // Storage succeeds
        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn(['path' => '/path/file.txt', 'neutralized' => false]);

        // Persistence
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        // Act
        $file = $this->service->createFromUpload($uploadedFile, $ownerId);

        // Assert: sanitized
        $this->assertStringNotContainsString("\x00", $file->getOriginalName());
        $this->assertStringNotContainsString("\x1F", $file->getOriginalName());
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
        $this->userRepository->expects($this->once())
            ->method('find')
            ->willReturn($owner);

        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->with($folderId, null, $owner)
            ->willReturn($targetFolder);

        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn(['path' => '/path/document.pdf', 'neutralized' => false]);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

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
        $this->userRepository->expects($this->once())
            ->method('find')
            ->willReturn($owner);

        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->with(null, 'New Folder', $owner)
            ->willReturn($newFolder);

        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn(['path' => '/path/image.jpg', 'neutralized' => false]);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        // Act
        $file = $this->service->createFromUpload(
            $uploadedFile,
            $ownerId,
            newFolderName: 'New Folder'
        );

        // Assert
        $this->assertEquals($newFolder, $file->getFolder());
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
        $this->userRepository->expects($this->once())
            ->method('find')
            ->willReturn($owner);

        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->willReturn($folder);

        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn([
                'path' => '/2026/03/uuid.txt',
                'neutralized' => false,
            ]);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

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
        $this->userRepository->expects($this->once())
            ->method('find')
            ->willReturn($owner);

        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->willReturn($folder);

        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn([
                'path' => '/2026/03/uuid.bin',
                'neutralized' => true,  // File was neutralized
            ]);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        // Act
        $file = $this->service->createFromUpload($uploadedFile, $ownerId);

        // Assert
        $this->assertTrue($file->isNeutralized());
    }
}
