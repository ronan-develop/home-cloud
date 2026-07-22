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
use RonanLenouvel\RawPreviewExtractor\ExtractedPreview;
use RonanLenouvel\RawPreviewExtractor\Format\Format;
use RonanLenouvel\RawPreviewExtractor\RawMetadata;
use RonanLenouvel\RawPreviewExtractor\RawPreviewExtractorInterface;

/**
 * Tests unitaires MediaProcessor — création de Media (EXIF + thumbnail).
 *
 * JPEG : EXIF via ExifService. RAW : exif_read_data ne sait pas ouvrir le
 * conteneur, les métadonnées viennent de RawPreviewExtractor (preview EXIF).
 */
final class MediaProcessorTest extends TestCase
{
    private function baseExif(array $overrides = []): array
    {
        return array_merge([
            'width' => null, 'height' => null, 'takenAt' => null,
            'cameraModel' => null, 'gpsLat' => null, 'gpsLon' => null,
            'aperture' => null, 'shutterSpeed' => null, 'iso' => null,
            'focalLength' => null, 'lens' => null,
        ], $overrides);
    }

    private function processor(
        MediaRepository $mediaRepo,
        EntityManagerInterface $em,
        ExifService $exifService,
        ThumbnailService $thumbnailService,
        StorageServiceInterface $storageService,
        ?RawPreviewExtractorInterface $rawExtractor = null,
    ): MediaProcessor {
        return new MediaProcessor(
            $mediaRepo,
            $em,
            $exifService,
            $thumbnailService,
            $storageService,
            $rawExtractor ?? $this->createStub(RawPreviewExtractorInterface::class),
        );
    }

    public function testProcessCreatesMediaForImage(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getMimeType')->willReturn('image/jpeg');
        $file->method('getOriginalName')->willReturn('photo.jpg');
        $file->method('getPath')->willReturn('2026/02/test.jpg');

        $mediaRepo = $this->createStub(MediaRepository::class);
        $mediaRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $storageService = $this->createStub(StorageServiceInterface::class);
        $storageService->method('getAbsolutePath')->willReturn('/var/storage/2026/02/test.jpg');

        $exifService = $this->createStub(ExifService::class);
        $exifService->method('extract')->willReturn($this->baseExif([
            'width' => 1920, 'height' => 1080,
            'takenAt' => new \DateTimeImmutable('2024-06-15 12:00:00'),
            'cameraModel' => 'Apple iPhone 15',
            'gpsLat' => '48.8566000', 'gpsLon' => '2.3522000',
        ]));

        $thumbnailService = $this->createStub(ThumbnailService::class);
        $thumbnailService->method('generate')->willReturn('thumbs/test.jpg');

        $media = $this->processor($mediaRepo, $em, $exifService, $thumbnailService, $storageService)->process($file);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertSame('photo', $media->getMediaType());
    }

    /**
     * Pack photographe : un JPEG expose ouverture/vitesse/ISO/focale/objectif.
     */
    public function testProcessMapsPhotographerSettingsForJpeg(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getMimeType')->willReturn('image/jpeg');
        $file->method('getOriginalName')->willReturn('photo.jpg');
        $file->method('getPath')->willReturn('2026/02/test.jpg');

        $mediaRepo = $this->createStub(MediaRepository::class);
        $mediaRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $storageService = $this->createStub(StorageServiceInterface::class);
        $storageService->method('getAbsolutePath')->willReturn('/var/storage/2026/02/test.jpg');

        $exifService = $this->createStub(ExifService::class);
        $exifService->method('extract')->willReturn($this->baseExif([
            'aperture' => '2.8', 'shutterSpeed' => '1/250', 'iso' => 400,
            'focalLength' => '50', 'lens' => 'RF50mm F1.8 STM',
        ]));

        $thumbnailService = $this->createStub(ThumbnailService::class);
        $thumbnailService->method('generate')->willReturn('thumbs/test.jpg');

        $media = $this->processor($mediaRepo, $em, $exifService, $thumbnailService, $storageService)->process($file);

        $this->assertSame('2.8', $media->getAperture());
        $this->assertSame('1/250', $media->getShutterSpeed());
        $this->assertSame(400, $media->getIso());
        $this->assertSame('50', $media->getFocalLength());
        $this->assertSame('RF50mm F1.8 STM', $media->getLens());
    }

    public function testProcessReturnsNullForNonMediaFile(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getMimeType')->willReturn('application/pdf');
        $file->method('getOriginalName')->willReturn('doc.pdf');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $media = $this->processor(
            $this->createStub(MediaRepository::class),
            $em,
            $this->createStub(ExifService::class),
            $this->createStub(ThumbnailService::class),
            $this->createStub(StorageServiceInterface::class),
        )->process($file);

        $this->assertNull($media);
    }

    public function testProcessReturnsExistingMediaIfAlreadyProcessed(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getMimeType')->willReturn('image/jpeg');
        $file->method('getOriginalName')->willReturn('photo.jpg');

        $existingMedia = $this->createStub(Media::class);

        $mediaRepo = $this->createStub(MediaRepository::class);
        $mediaRepo->method('findOneBy')->willReturn($existingMedia);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $media = $this->processor(
            $mediaRepo,
            $em,
            $this->createStub(ExifService::class),
            $this->createStub(ThumbnailService::class),
            $this->createStub(StorageServiceInterface::class),
        )->process($file);

        $this->assertSame($existingMedia, $media);
    }

    /**
     * RAW : les métadonnées (date, réglages, appareil, objectif) viennent de
     * RawPreviewExtractor, pas d'exif_read_data. La vignette reste générée
     * depuis le fichier RAW d'origine.
     */
    #[DataProvider('rawFileProvider')]
    public function testProcessMapsRawMetadataFromPackage(string $mimeType, string $filename): void
    {
        $file = $this->createStub(File::class);
        $file->method('getMimeType')->willReturn($mimeType);
        $file->method('getOriginalName')->willReturn($filename);
        $file->method('getPath')->willReturn('2026/02/'.$filename);

        $mediaRepo = $this->createStub(MediaRepository::class);
        $mediaRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);

        $storageService = $this->createStub(StorageServiceInterface::class);
        $storageService->method('getAbsolutePath')->willReturn('/var/storage/2026/02/'.$filename);

        // Sur un RAW, exif_read_data ne doit pas être la source : ExifService
        // n'est jamais consulté.
        $exifService = $this->createMock(ExifService::class);
        $exifService->expects($this->never())->method('extract');

        $preview = new ExtractedPreview(
            'jpegbytes', 6000, 4000, Format::NEF,
            metadata: new RawMetadata(
                dateTimeOriginal: '2024:06:15 12:30:45',
                fNumber: 2.8,
                exposureTime: '1/250',
                iso: 400,
                focalLength: 50.0,
                lensModel: 'NIKKOR Z 50mm f/1.8 S',
                cameraMake: 'NIKON',
                cameraModel: 'Z 6',
            ),
        );

        $rawExtractor = $this->createStub(RawPreviewExtractorInterface::class);
        $rawExtractor->method('extract')->willReturn($preview);

        $thumbnailService = $this->createStub(ThumbnailService::class);
        $thumbnailService->method('generate')->willReturn('thumbs/raw.jpg');

        $media = $this->processor($mediaRepo, $em, $exifService, $thumbnailService, $storageService, $rawExtractor)
            ->process($file);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertSame('photo', $media->getMediaType());
        $this->assertSame('thumbs/raw.jpg', $media->getThumbnailPath());
        $this->assertSame('2.8', $media->getAperture());
        $this->assertSame('1/250', $media->getShutterSpeed());
        $this->assertSame(400, $media->getIso());
        $this->assertSame('50', $media->getFocalLength());
        $this->assertSame('NIKKOR Z 50mm f/1.8 S', $media->getLens());
        $this->assertSame('NIKON Z 6', $media->getCameraModel());
        $this->assertEquals(new \DateTimeImmutable('2024-06-15 12:30:45'), $media->getTakenAt());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rawFileProvider(): iterable
    {
        yield 'NEF en octet-stream' => ['application/octet-stream', 'DSC_0001.NEF'];
        yield 'CR2 en octet-stream' => ['application/octet-stream', 'IMG_0042.CR2'];
        yield 'extension minuscule' => ['application/octet-stream', 'photo.nef'];
    }

    /**
     * RAW illisible ou sans métadonnées : dégradation gracieuse, Media créé
     * sans réglages, vignette tout de même tentée.
     */
    public function testProcessToleratesRawWithoutMetadata(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getMimeType')->willReturn('application/octet-stream');
        $file->method('getOriginalName')->willReturn('broken.nef');
        $file->method('getPath')->willReturn('2026/02/broken.nef');

        $mediaRepo = $this->createStub(MediaRepository::class);
        $mediaRepo->method('findOneBy')->willReturn(null);

        $storageService = $this->createStub(StorageServiceInterface::class);
        $storageService->method('getAbsolutePath')->willReturn('/var/storage/2026/02/broken.nef');

        $rawExtractor = $this->createStub(RawPreviewExtractorInterface::class);
        $rawExtractor->method('extract')->willThrowException(new \RuntimeException('unreadable'));

        $thumbnailService = $this->createStub(ThumbnailService::class);
        $thumbnailService->method('generate')->willReturn(null);

        $media = $this->processor(
            $mediaRepo,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(ExifService::class),
            $thumbnailService,
            $storageService,
            $rawExtractor,
        )->process($file);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertSame('photo', $media->getMediaType());
        $this->assertNull($media->getAperture());
    }

    public function testProcessStillRejectsNonRawOctetStream(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getMimeType')->willReturn('application/octet-stream');
        $file->method('getOriginalName')->willReturn('archive.zip');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $media = $this->processor(
            $this->createStub(MediaRepository::class),
            $em,
            $this->createStub(ExifService::class),
            $this->createStub(ThumbnailService::class),
            $this->createStub(StorageServiceInterface::class),
        )->process($file);

        $this->assertNull($media);
    }

    public function testProcessCreatesVideoMediaWithThumbnailButNoExif(): void
    {
        $file = $this->createStub(File::class);
        $file->method('getMimeType')->willReturn('video/mp4');
        $file->method('getOriginalName')->willReturn('clip.mp4');
        $file->method('getPath')->willReturn('2026/02/clip.mp4');

        $mediaRepo = $this->createStub(MediaRepository::class);
        $mediaRepo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $exifService = $this->createMock(ExifService::class);
        $exifService->expects($this->never())->method('extract');
        $thumbnailService = $this->createMock(ThumbnailService::class);
        $thumbnailService->expects($this->once())->method('generate')->willReturn('thumbs/abc.jpg');

        $media = $this->processor(
            $mediaRepo,
            $em,
            $exifService,
            $thumbnailService,
            $this->createStub(StorageServiceInterface::class),
        )->process($file);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertSame('video', $media->getMediaType());
        $this->assertSame('thumbs/abc.jpg', $media->getThumbnailPath());
    }

    /**
     * @see \App\Service\MediaProcessor::isRaw
     */
    #[DataProvider('isRawProvider')]
    public function testIsRawRecognisesRawExtensionsCaseInsensitively(string $filename, bool $expected): void
    {
        $processor = $this->processor(
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
        yield 'nef minuscule' => ['photo.nef', true];
        yield 'NEF majuscule' => ['PHOTO.NEF', true];
        yield 'cr2 casse mixte' => ['img.Cr2', true];
        yield 'jpg non raw' => ['photo.jpg', false];
        yield 'sans extension' => ['README', false];
    }
}
