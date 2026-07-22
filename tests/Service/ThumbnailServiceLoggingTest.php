<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\Video\FrameExtractionFailedException;
use App\Interface\ExifThumbnailExtractorInterface;
use App\Interface\VideoThumbnailExtractorInterface;
use App\Service\ThumbnailService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RonanLenouvel\RawPreviewExtractor\Exception\PreviewNotFoundException;
use RonanLenouvel\RawPreviewExtractor\RawPreviewExtractorInterface;

/**
 * Jusqu'ici, un échec d'extraction (RAW illisible, ffmpeg absent, vidéo
 * corrompue) était avalé silencieusement : le Media était créé sans vignette,
 * sans aucune trace exploitable. Observé en prod sur #312 — impossible de
 * savoir depuis les logs pourquoi une vidéo n'avait pas de thumbnail.
 */
final class ThumbnailServiceLoggingTest extends TestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/hc-thumb-log-'.uniqid();
        mkdir($this->storageDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $thumbsDir = $this->storageDir.'/thumbs';
        if (is_dir($thumbsDir)) {
            array_map('unlink', glob($thumbsDir.'/*') ?: []);
            rmdir($thumbsDir);
        }
        array_map('unlink', glob($this->storageDir.'/*') ?: []);
        if (is_dir($this->storageDir)) {
            rmdir($this->storageDir);
        }
    }

    private function makeVideoFile(): string
    {
        $path = $this->storageDir.'/video.mp4';
        file_put_contents($path, 'not-a-real-video');

        return $path;
    }

    public function testLogsWarningWithCauseWhenVideoExtractionFails(): void
    {
        $videoPath = $this->makeVideoFile();

        $videoExtractor = $this->createMock(VideoThumbnailExtractorInterface::class);
        $videoExtractor->method('supports')->willReturn(true);
        $videoExtractor->method('extract')
            ->willThrowException(new FrameExtractionFailedException('ffmpeg a échoué : exit 1'));

        $rawExtractor = $this->createMock(RawPreviewExtractorInterface::class);
        $rawExtractor->method('supports')->willReturn(false);

        $exifExtractor = $this->createMock(ExifThumbnailExtractorInterface::class);
        $exifExtractor->method('extract')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('vignette'),
                $this->callback(function (array $context) use ($videoPath) {
                    return ($context['path'] ?? null) === $videoPath
                        && ($context['exception'] ?? null) instanceof FrameExtractionFailedException;
                }),
            );

        $service = new ThumbnailService($this->storageDir, $rawExtractor, $exifExtractor, $videoExtractor, $logger);
        $thumb = $service->generate($videoPath);

        $this->assertNull($thumb);
    }

    public function testLogsWarningWithCauseWhenRawExtractionFails(): void
    {
        $rawPath = $this->storageDir.'/photo.nef';
        file_put_contents($rawPath, 'not-a-real-raw');

        $rawExtractor = $this->createMock(RawPreviewExtractorInterface::class);
        $rawExtractor->method('supports')->willReturn(true);
        $rawExtractor->method('extract')
            ->willThrowException(new PreviewNotFoundException('no preview'));

        $exifExtractor = $this->createMock(ExifThumbnailExtractorInterface::class);
        $exifExtractor->method('extract')->willReturn(null);

        $videoExtractor = $this->createMock(VideoThumbnailExtractorInterface::class);
        $videoExtractor->method('supports')->willReturn(false);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('vignette'),
                $this->callback(function (array $context) use ($rawPath) {
                    return ($context['path'] ?? null) === $rawPath
                        && ($context['exception'] ?? null) instanceof PreviewNotFoundException;
                }),
            );

        $service = new ThumbnailService($this->storageDir, $rawExtractor, $exifExtractor, $videoExtractor, $logger);
        $service->generate($rawPath);
    }

    public function testDoesNotLogWhenThumbnailGenerationSucceeds(): void
    {
        $videoPath = $this->makeVideoFile();

        $videoExtractor = $this->createMock(VideoThumbnailExtractorInterface::class);
        $videoExtractor->method('supports')->willReturn(false);

        $rawExtractor = $this->createMock(RawPreviewExtractorInterface::class);
        $rawExtractor->method('supports')->willReturn(false);

        $exifExtractor = $this->createMock(ExifThumbnailExtractorInterface::class);
        $exifExtractor->method('extract')->willReturn(null);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $image = imagecreatetruecolor(400, 300);
        ob_start();
        imagejpeg($image);
        $data = (string) ob_get_clean();
        imagedestroy($image);
        $jpegPath = $this->storageDir.'/photo.jpg';
        file_put_contents($jpegPath, $data);

        $service = new ThumbnailService($this->storageDir, $rawExtractor, $exifExtractor, $videoExtractor, $logger);
        $thumb = $service->generate($jpegPath);

        $this->assertNotNull($thumb);
    }

    public function testLoggerDefaultsToNullLoggerWhenNotProvided(): void
    {
        // Rétrocompatibilité : les sites d'instanciation existants (tests,
        // services.yaml sans binding explicite) ne doivent pas casser.
        $rawExtractor = $this->createMock(RawPreviewExtractorInterface::class);
        $rawExtractor->method('supports')->willReturn(false);

        $exifExtractor = $this->createMock(ExifThumbnailExtractorInterface::class);
        $exifExtractor->method('extract')->willReturn(null);

        $videoExtractor = $this->createMock(VideoThumbnailExtractorInterface::class);
        $videoExtractor->method('supports')->willReturn(false);

        $service = new ThumbnailService($this->storageDir, $rawExtractor, $exifExtractor, $videoExtractor);

        $this->assertInstanceOf(ThumbnailService::class, $service);
    }
}
