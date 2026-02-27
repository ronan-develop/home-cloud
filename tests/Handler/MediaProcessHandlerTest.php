<?php

declare(strict_types=1);

namespace App\Tests\Handler;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Handler\MediaProcessHandler;
use App\Message\MediaProcessMessage;
use App\Repository\FileRepository;
use App\Repository\MediaRepository;
use App\Service\ExifService;
use App\Service\ThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class MediaProcessHandlerTest extends TestCase
{
    public function testHandlerCreatesMediaForImage(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getId')->willReturn(\Symfony\Component\Uid\Uuid::v7());
        $file->method('getMimeType')->willReturn('image/jpeg');
        $file->method('getPath')->willReturn('2026/02/test.jpg');

        $fileRepo = $this->createMock(FileRepository::class);
        $fileRepo->method('find')->willReturn($file);

        $mediaRepo = $this->createMock(MediaRepository::class);
        $mediaRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $exifService = $this->createMock(ExifService::class);
        $exifService->method('extract')->willReturn([
            'width' => 1920,
            'height' => 1080,
            'takenAt' => new \DateTimeImmutable('2024-06-15 12:00:00'),
            'cameraModel' => 'Apple iPhone 15',
            'gpsLat' => '48.8566000',
            'gpsLon' => '2.3522000',
        ]);

        $thumbnailService = $this->createMock(ThumbnailService::class);
        $thumbnailService->method('generate')->willReturn('thumbs/test.jpg');

        $handler = new MediaProcessHandler($fileRepo, $mediaRepo, $em, $exifService, $thumbnailService);
        $handler(new MediaProcessMessage('some-uuid'));
    }

    public function testHandlerIgnoresNonMediaFile(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getMimeType')->willReturn('application/pdf');

        $fileRepo = $this->createMock(FileRepository::class);
        $fileRepo->method('find')->willReturn($file);

        $mediaRepo = $this->createMock(MediaRepository::class);
        $mediaRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $exifService = $this->createMock(ExifService::class);
        $thumbnailService = $this->createMock(ThumbnailService::class);

        $handler = new MediaProcessHandler($fileRepo, $mediaRepo, $em, $exifService, $thumbnailService);
        $handler(new MediaProcessMessage('some-uuid'));
    }

    public function testHandlerSkipsAlreadyProcessedFile(): void
    {
        $file = $this->createMock(File::class);
        $file->method('getMimeType')->willReturn('image/jpeg');

        $existingMedia = $this->createMock(\App\Entity\Media::class);

        $fileRepo = $this->createMock(FileRepository::class);
        $fileRepo->method('find')->willReturn($file);

        $mediaRepo = $this->createMock(MediaRepository::class);
        $mediaRepo->method('findOneBy')->willReturn($existingMedia);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $exifService = $this->createMock(ExifService::class);
        $thumbnailService = $this->createMock(ThumbnailService::class);

        $handler = new MediaProcessHandler($fileRepo, $mediaRepo, $em, $exifService, $thumbnailService);
        $handler(new MediaProcessMessage('some-uuid'));
    }
}
