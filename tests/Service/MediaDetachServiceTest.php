<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\Share;
use App\Entity\User;
use App\Interface\SharedResourceCleanerInterface;
use App\Interface\StorageServiceInterface;
use App\Service\MediaDetachService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class MediaDetachServiceTest extends TestCase
{
    private function makeMedia(): Media
    {
        $owner  = new User('test@example.com', 'Test');
        $folder = new Folder('Photos', $owner);
        $file   = new File('photo.jpg', 'image/jpeg', 1024, '2026/02/photo.jpg', $folder, $owner);

        return new Media($file, 'photo');
    }

    public function testDetachAndDeleteFileRemovesFileFromDisk(): void
    {
        $media = $this->makeMedia();

        $storage = $this->createMock(StorageServiceInterface::class);
        $storage->expects($this->once())->method('delete')->with('2026/02/photo.jpg');

        $em = $this->createMock(EntityManagerInterface::class);
        $sharedResourceCleaner = $this->createMock(SharedResourceCleanerInterface::class);

        $service = new MediaDetachService($storage, $sharedResourceCleaner, $em);
        $service->detachAndDeleteFile($media);
    }

    public function testDetachAndDeleteFileCleansSharedResource(): void
    {
        $media = $this->makeMedia();
        $fileId = $media->getFile()->getId();

        $storage = $this->createMock(StorageServiceInterface::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $sharedResourceCleaner = $this->createMock(SharedResourceCleanerInterface::class);
        $sharedResourceCleaner->expects($this->once())
            ->method('deleteByResource')
            ->with(Share::RESOURCE_FILE, $fileId);

        $service = new MediaDetachService($storage, $sharedResourceCleaner, $em);
        $service->detachAndDeleteFile($media);
    }

    public function testDetachAndDeleteFileSetsMediaFileToNull(): void
    {
        $media = $this->makeMedia();

        $service = new MediaDetachService(
            $this->createMock(StorageServiceInterface::class),
            $this->createMock(SharedResourceCleanerInterface::class),
            $this->createMock(EntityManagerInterface::class),
        );
        $service->detachAndDeleteFile($media);

        $this->assertNull($media->getFile());
    }

    public function testDetachAndDeleteFileRemovesFileEntityAndFlushesOnce(): void
    {
        $media = $this->makeMedia();
        $file  = $media->getFile();

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($file);
        $em->expects($this->once())->method('flush');

        $service = new MediaDetachService(
            $this->createMock(StorageServiceInterface::class),
            $this->createMock(SharedResourceCleanerInterface::class),
            $em,
        );
        $service->detachAndDeleteFile($media);
    }

    public function testDetachAndDeleteFileIsGracefulWhenFileMissingOnDisk(): void
    {
        $media = $this->makeMedia();

        $storage = $this->createMock(StorageServiceInterface::class);
        $storage->method('delete')->willThrowException(new \RuntimeException('File not found'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove');
        $em->expects($this->once())->method('flush');

        $service = new MediaDetachService(
            $storage,
            $this->createMock(SharedResourceCleanerInterface::class),
            $em,
        );
        $service->detachAndDeleteFile($media);

        $this->assertNull($media->getFile());
    }

    public function testDetachAndDeleteFileThrowsWhenMediaAlreadyDetached(): void
    {
        $media = $this->makeMedia();
        $media->detach();

        $service = new MediaDetachService(
            $this->createMock(StorageServiceInterface::class),
            $this->createMock(SharedResourceCleanerInterface::class),
            $this->createMock(EntityManagerInterface::class),
        );

        $this->expectException(\LogicException::class);
        $service->detachAndDeleteFile($media);
    }
}
