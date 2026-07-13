<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\File;
use App\Entity\Media;
use App\Interface\StorageServiceInterface;
use App\Repository\MediaRepository;
use App\Service\ExifService;
use App\Service\MediaProcessor;
use App\Service\ThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires MediaProcessor — logique de création de Media (EXIF +
 * thumbnail) extraite de MediaProcessHandler pour être appelable aussi bien
 * en asynchrone (handler Messenger) qu'en synchrone (import direct).
 */
final class MediaProcessorTest extends TestCase
{
    public function testProcessCreatesMediaForImage(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getMimeType')->willReturn('image/jpeg');
        $file->method('getPath')->willReturn('2026/02/test.jpg');

        $mediaRepo = $this->createMock(MediaRepository::class);
        $mediaRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $storageService = $this->createMock(StorageServiceInterface::class);
        $storageService->expects($this->once())->method('getAbsolutePath')->with('2026/02/test.jpg')->willReturn('/var/storage/2026/02/test.jpg');

        $exifService = $this->createMock(ExifService::class);
        $exifService->expects($this->once())->method('extract')->with('/var/storage/2026/02/test.jpg')->willReturn([
            'width' => 1920,
            'height' => 1080,
            'takenAt' => new \DateTimeImmutable('2024-06-15 12:00:00'),
            'cameraModel' => 'Apple iPhone 15',
            'gpsLat' => '48.8566000',
            'gpsLon' => '2.3522000',
        ]);

        $thumbnailService = $this->createMock(ThumbnailService::class);
        $thumbnailService->expects($this->once())->method('generate')->with('/var/storage/2026/02/test.jpg')->willReturn('thumbs/test.jpg');

        $processor = new MediaProcessor($mediaRepo, $em, $exifService, $thumbnailService, $storageService);
        $media = $processor->process($file);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertSame('photo', $media->getMediaType());
    }

    public function testProcessReturnsNullForNonMediaFile(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getMimeType')->willReturn('application/pdf');

        $mediaRepo = $this->createMock(MediaRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $exifService = $this->createMock(ExifService::class);
        $thumbnailService = $this->createMock(ThumbnailService::class);
        $storageService = $this->createMock(StorageServiceInterface::class);

        $processor = new MediaProcessor($mediaRepo, $em, $exifService, $thumbnailService, $storageService);
        $media = $processor->process($file);

        $this->assertNull($media);
    }

    public function testProcessReturnsExistingMediaIfAlreadyProcessed(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getMimeType')->willReturn('image/jpeg');

        $existingMedia = $this->createMock(Media::class);

        $mediaRepo = $this->createMock(MediaRepository::class);
        $mediaRepo->method('findOneBy')->willReturn($existingMedia);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $exifService = $this->createMock(ExifService::class);
        $thumbnailService = $this->createMock(ThumbnailService::class);
        $storageService = $this->createMock(StorageServiceInterface::class);

        $processor = new MediaProcessor($mediaRepo, $em, $exifService, $thumbnailService, $storageService);
        $media = $processor->process($file);

        $this->assertSame($existingMedia, $media);
    }

    public function testProcessCreatesVideoMediaWithoutExifOrThumbnail(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getMimeType')->willReturn('video/mp4');
        $file->method('getPath')->willReturn('2026/02/clip.mp4');

        $mediaRepo = $this->createMock(MediaRepository::class);
        $mediaRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $exifService = $this->createMock(ExifService::class);
        $exifService->expects($this->never())->method('extract');
        $thumbnailService = $this->createMock(ThumbnailService::class);
        $thumbnailService->expects($this->never())->method('generate');
        $storageService = $this->createMock(StorageServiceInterface::class);

        $processor = new MediaProcessor($mediaRepo, $em, $exifService, $thumbnailService, $storageService);
        $media = $processor->process($file);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertSame('video', $media->getMediaType());
    }
}
