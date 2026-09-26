<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Folder;
use App\Entity\User;
use App\Interface\DefaultFolderServiceInterface;
use App\Interface\StorageServiceInterface;
use App\Repository\ContentFingerprintRepository;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * #440 — FileUploadService : symétrique à CreateFileService côté web.
 * Absorbe la validation d'extension bloquée et le nettoyage de nom
 * jusqu'ici en dur dans FileWebController::upload().
 *
 * #327 — absorbe aussi la détection de doublons par fingerprint persistant.
 */
final class FileUploadServiceTest extends TestCase
{
    private FileUploadService $service;
    private StorageServiceInterface $storageService;
    private DefaultFolderServiceInterface $defaultFolderService;
    private EntityManagerInterface $em;
    private ContentFingerprintRepository $contentFingerprintRepository;

    protected function setUp(): void
    {
        $this->storageService = $this->createMock(StorageServiceInterface::class);
        $this->defaultFolderService = $this->createMock(DefaultFolderServiceInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->contentFingerprintRepository = $this->createMock(ContentFingerprintRepository::class);
        $this->contentFingerprintRepository->method('existsForOwner')->willReturn(false);

        $this->service = new FileUploadService(
            $this->storageService,
            $this->defaultFolderService,
            $this->em,
            $this->contentFingerprintRepository,
        );
    }

    public function testCreateFromUploadRejectsBlockedExtension(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'contenu');
        $uploadedFile = new UploadedFile($tmpFile, 'malware.exe', 'application/octet-stream', null, true);

        $owner = new User('owner@example.com', 'Owner');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('File type ".exe" is not allowed.');

        $this->service->createFromUpload($uploadedFile, $owner);
    }

    public function testCreateFromUploadSanitizesFileName(): void
    {
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
        $folder = new Folder('Root', $owner);

        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->willReturn($folder);

        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn(['path' => '/path/file.txt', 'neutralized' => false]);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $file = $this->service->createFromUpload($uploadedFile, $owner);

        $this->assertStringNotContainsString("\x00", $file->getOriginalName());
        $this->assertStringNotContainsString("\x1F", $file->getOriginalName());
        $this->assertStringNotContainsString('<', $file->getOriginalName());
        $this->assertStringNotContainsString('>', $file->getOriginalName());
    }

    public function testCreateFromUploadWithFolderId(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'content');
        $uploadedFile = new UploadedFile($tmpFile, 'document.pdf', 'application/pdf', null, true);

        $owner = new User('owner@example.com', 'Owner');
        $targetFolder = new Folder('Documents', $owner);
        $folderId = (string) $targetFolder->getId();

        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->with($folderId, null, $owner)
            ->willReturn($targetFolder);

        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn(['path' => '/path/document.pdf', 'neutralized' => false]);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $file = $this->service->createFromUpload($uploadedFile, $owner, $folderId);

        $this->assertEquals($targetFolder, $file->getFolder());
    }

    public function testCreateFromUploadPersistsFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'file content');
        $uploadedFile = new UploadedFile($tmpFile, 'data.txt', 'text/plain', null, true);

        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('Root', $owner);

        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->willReturn($folder);

        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn(['path' => '/2026/03/uuid.txt', 'neutralized' => false]);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $file = $this->service->createFromUpload($uploadedFile, $owner);

        $this->assertEquals('data.txt', $file->getOriginalName());
        $this->assertEquals('text/plain', $file->getMimeType());
        $this->assertEquals('/2026/03/uuid.txt', $file->getPath());
        $this->assertFalse($file->isNeutralized());
        $this->assertEquals($owner, $file->getOwner());
    }

    public function testCreateFromUploadMarksNeutralizedFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, '<svg>...</svg>');
        $uploadedFile = new UploadedFile($tmpFile, 'image.svg', 'image/svg+xml', null, true);

        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('Root', $owner);

        $this->defaultFolderService->expects($this->once())
            ->method('resolve')
            ->willReturn($folder);

        $this->storageService->expects($this->once())
            ->method('store')
            ->willReturn(['path' => '/2026/03/uuid.bin', 'neutralized' => true]);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $file = $this->service->createFromUpload($uploadedFile, $owner);

        $this->assertTrue($file->isNeutralized());
    }

    public function testCreateFromUploadRejectsDuplicateContentForSameOwner(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'contenu déjà importé');
        $uploadedFile = new UploadedFile($tmpFile, 'doublon.txt', 'text/plain', null, true);

        $owner = new User('owner@example.com', 'Owner');

        $this->contentFingerprintRepository = $this->createMock(ContentFingerprintRepository::class);
        $this->contentFingerprintRepository->expects($this->once())
            ->method('existsForOwner')
            ->with($owner, hash_file('sha256', $tmpFile))
            ->willReturn(true);

        $this->service = new FileUploadService(
            $this->storageService,
            $this->defaultFolderService,
            $this->em,
            $this->contentFingerprintRepository,
        );

        $this->storageService->expects($this->never())->method('store');
        $this->em->expects($this->never())->method('persist');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Ce fichier a déjà été importé.');

        $this->service->createFromUpload($uploadedFile, $owner);
    }

    public function testCreateFromUploadRecordsFingerprintAfterSuccessfulUpload(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'contenu nouveau');
        $uploadedFile = new UploadedFile($tmpFile, 'nouveau.txt', 'text/plain', null, true);

        $owner = new User('owner@example.com', 'Owner');
        $folder = new Folder('Root', $owner);

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

        $this->service = new FileUploadService(
            $this->storageService,
            $this->defaultFolderService,
            $this->em,
            $this->contentFingerprintRepository,
        );

        $this->service->createFromUpload($uploadedFile, $owner);
    }
}
