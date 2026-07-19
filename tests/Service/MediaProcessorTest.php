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
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * Les navigateurs n'ont pas de mimeType pour les RAW : un .NEF arrive en
     * "application/octet-stream", que resolveMediaType() rejetait — aucun Media
     * n'était créé, donc aucune vignette n'était même tentée. L'extension prend
     * le relais quand le mimeType ne dit rien.
     */
    #[DataProvider('rawFileProvider')]
    public function testProcessCreatesPhotoMediaForRawFile(string $mimeType, string $filename): void
    {
        $file = $this->createMock(File::class);
        $file->method('getMimeType')->willReturn($mimeType);
        $file->method('getOriginalName')->willReturn($filename);
        $file->method('getPath')->willReturn('2026/02/' . $filename);

        $mediaRepo = $this->createMock(MediaRepository::class);
        $mediaRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $storageService = $this->createMock(StorageServiceInterface::class);
        $storageService->method('getAbsolutePath')->willReturn('/var/storage/2026/02/' . $filename);

        $exifService = $this->createMock(ExifService::class);
        $exifService->method('extract')->willReturn([
            'width' => null, 'height' => null, 'takenAt' => null,
            'cameraModel' => null, 'gpsLat' => null, 'gpsLon' => null,
        ]);

        // Le cœur du besoin : la vignette doit être tentée sur un RAW.
        $thumbnailService = $this->createMock(ThumbnailService::class);
        $thumbnailService->expects($this->once())->method('generate')->willReturn('thumbs/raw.jpg');

        $processor = new MediaProcessor($mediaRepo, $em, $exifService, $thumbnailService, $storageService);
        $media = $processor->process($file);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertSame('photo', $media->getMediaType());
        $this->assertSame('thumbs/raw.jpg', $media->getThumbnailPath());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rawFileProvider(): iterable
    {
        // Ce que les navigateurs envoient réellement pour un RAW.
        yield 'NEF en octet-stream'  => ['application/octet-stream', 'DSC_0001.NEF'];
        yield 'CR2 en octet-stream'  => ['application/octet-stream', 'IMG_0042.CR2'];
        yield 'CR3 en octet-stream'  => ['application/octet-stream', 'IMG_0043.CR3'];
        yield 'ARW en octet-stream'  => ['application/octet-stream', 'DSC01234.ARW'];
        yield 'DNG en octet-stream'  => ['application/octet-stream', 'shot.dng'];
        // Certains clients détectent le conteneur TIFF sous-jacent.
        yield 'NEF vu comme tiff'    => ['image/tiff', 'DSC_0002.NEF'];
        // Extension en minuscules ou casse mixte.
        yield 'extension minuscule'  => ['application/octet-stream', 'photo.nef'];
        yield 'casse mixte'          => ['application/octet-stream', 'photo.Cr2'];
    }

    public function testProcessStillRejectsNonRawOctetStream(): void
    {
        // Garde-fou : élargir la détection ne doit pas transformer n'importe quel
        // binaire inconnu en photo.
        $file = $this->createMock(File::class);
        $file->method('getMimeType')->willReturn('application/octet-stream');
        $file->method('getOriginalName')->willReturn('archive.zip');

        $mediaRepo = $this->createMock(MediaRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $exifService = $this->createMock(ExifService::class);
        $thumbnailService = $this->createMock(ThumbnailService::class);
        $storageService = $this->createMock(StorageServiceInterface::class);

        $processor = new MediaProcessor($mediaRepo, $em, $exifService, $thumbnailService, $storageService);

        $this->assertNull($processor->process($file));
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

    /**
     * `isRaw()` expose publiquement la reconnaissance des RAW (jusqu'ici privée)
     * pour que la décision de routage (UploadRoutingDecider) s'appuie sur cette
     * seule source de vérité plutôt que de redupliquer la liste RAW_EXTENSIONS.
     */
    #[DataProvider('isRawProvider')]
    public function testIsRawRecognisesRawExtensionsCaseInsensitively(string $filename, bool $expected): void
    {
        $processor = new MediaProcessor(
            $this->createStub(MediaRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(ExifService::class),
            $this->createStub(ThumbnailService::class),
            $this->createStub(StorageServiceInterface::class),
        );

        $this->assertSame($expected, $processor->isRaw($filename));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function isRawProvider(): iterable
    {
        yield 'nef minuscule'   => ['photo.nef', true];
        yield 'NEF majuscule'   => ['PHOTO.NEF', true];
        yield 'cr2 casse mixte' => ['img.Cr2', true];
        yield 'cr3'             => ['clip.cr3', true];
        yield 'arw'             => ['DSC01234.arw', true];
        yield 'dng'             => ['shot.dng', true];
        yield 'jpg non raw'     => ['photo.jpg', false];
        yield 'tiff hors liste' => ['scan.tiff', false];
        yield 'sans extension'  => ['README', false];
    }
}
