<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\User;
use App\Interface\StorageServiceInterface;
use App\Service\MediaDeletionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class MediaDeletionServiceTest extends TestCase
{
    private function makeMedia(?string $thumbnailPath = null): Media
    {
        $owner  = new User('test@example.com', 'Test');
        $folder = new Folder('Photos', $owner);
        $file   = new File('photo.jpg', 'image/jpeg', 1024, '2026/02/photo.jpg', $folder, $owner);
        $media  = new Media($file, 'photo');

        if ($thumbnailPath !== null) {
            $media->setThumbnailPath($thumbnailPath);
        }

        return $media;
    }

    public function testDeleteRemovesOriginalFileFromDisk(): void
    {
        $media = $this->makeMedia();

        $storage = $this->createMock(StorageServiceInterface::class);
        $storage->expects($this->once())->method('delete')->with('2026/02/photo.jpg');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($media);
        $em->expects($this->once())->method('flush');

        $service = new MediaDeletionService($storage, $em);
        $service->delete($media);
    }

    public function testDeleteRemovesThumbnailFromDiskWhenPresent(): void
    {
        $media = $this->makeMedia('thumbs/abc.jpg');

        $storage = $this->createMock(StorageServiceInterface::class);
        $storage->expects($this->exactly(2))->method('delete')
            ->willReturnCallback(function (string $path) {
                static $expected = ['2026/02/photo.jpg', 'thumbs/abc.jpg'];
                $this->assertContains($path, $expected);
            });

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('remove');
        $em->method('flush');

        $service = new MediaDeletionService($storage, $em);
        $service->delete($media);
    }

    public function testDeleteDoesNotFailWhenNoThumbnail(): void
    {
        $media = $this->makeMedia(null);

        $storage = $this->createMock(StorageServiceInterface::class);
        $storage->expects($this->once())->method('delete')->with('2026/02/photo.jpg');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('remove');
        $em->method('flush');

        $service = new MediaDeletionService($storage, $em);
        $service->delete($media);
    }

    public function testDeleteIsGracefulWhenFileMissingOnDisk(): void
    {
        $media = $this->makeMedia();

        $storage = $this->createMock(StorageServiceInterface::class);
        $storage->method('delete')->willThrowException(new \RuntimeException('File not found'));

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('remove')->with($media);
        $em->expects($this->once())->method('flush');

        $service = new MediaDeletionService($storage, $em);
        $service->delete($media);
    }
}
