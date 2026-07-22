<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\Video\FrameExtractionFailedException;
use App\Interface\ExifThumbnailExtractorInterface;
use App\Interface\VideoThumbnailExtractorInterface;
use App\Service\ThumbnailService;
use App\Service\Video\ExtractedVideoFrame;
use PHPUnit\Framework\TestCase;
use RonanLenouvel\RawPreviewExtractor\RawPreviewExtractorInterface;

class ThumbnailServiceVideoTest extends TestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir().'/hc-test-thumbs-'.uniqid();
        mkdir($this->storageDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->storageDir) && is_dir($this->storageDir)) {
            array_map('unlink', glob($this->storageDir.'/*') ?: []);
            rmdir($this->storageDir);
        }
    }

    private function makeJpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
        ob_start();
        imagejpeg($image, null, 90);
        $data = ob_get_clean();
        imagedestroy($image);

        return $data ?: '';
    }

    private function makeVideoFile(): string
    {
        $path = $this->storageDir.'/video.mp4';
        file_put_contents($path, 'not-a-real-video-the-extractor-is-a-mock');

        return $path;
    }

    private function service(VideoThumbnailExtractorInterface $videoExtractor): ThumbnailService
    {
        $rawExtractor = $this->createMock(RawPreviewExtractorInterface::class);
        $rawExtractor->method('supports')->willReturn(false);

        $exifExtractor = $this->createMock(ExifThumbnailExtractorInterface::class);
        $exifExtractor->method('extract')->willReturn(null);

        return new ThumbnailService($this->storageDir, $rawExtractor, $exifExtractor, $videoExtractor);
    }

    public function testGenerateCreatesVideoThumbnailFromExtractedFrame(): void
    {
        $videoPath = $this->makeVideoFile();

        $videoExtractor = $this->createMock(VideoThumbnailExtractorInterface::class);
        $videoExtractor->method('supports')->willReturn(true);
        $videoExtractor->method('extract')
            ->willReturn(new ExtractedVideoFrame($this->makeJpeg(640, 480)));

        $service = $this->service($videoExtractor);

        $thumbnail = $service->generate($videoPath);

        $this->assertNotNull($thumbnail);
        $this->assertStringStartsWith('thumbs/', $thumbnail);
        $this->assertFileExists($this->storageDir.'/'.$thumbnail);
        $image = @imagecreatefromjpeg($this->storageDir.'/'.$thumbnail);
        $this->assertNotFalse($image);
        $width = imagesx($image);
        $this->assertSame(320, $width);
        imagedestroy($image);
    }

    public function testGenerateReturnsNullWhenVideoExtractionFails(): void
    {
        $videoPath = $this->makeVideoFile();

        $videoExtractor = $this->createMock(VideoThumbnailExtractorInterface::class);
        $videoExtractor->method('supports')->willReturn(true);
        $videoExtractor->method('extract')
            ->willThrowException(new FrameExtractionFailedException('ffmpeg failed'));

        $service = $this->service($videoExtractor);

        $thumbnail = $service->generate($videoPath);

        $this->assertNull($thumbnail);
    }

    public function testGenerateDoesNotExtractWhenVideoNotSupported(): void
    {
        $videoPath = $this->makeVideoFile();

        $videoExtractor = $this->createMock(VideoThumbnailExtractorInterface::class);
        $videoExtractor->method('supports')->willReturn(false);
        $videoExtractor->expects($this->never())->method('extract');

        $service = $this->service($videoExtractor);

        $thumbnail = $service->generate($videoPath);

        $this->assertNull($thumbnail);
    }

    public function testGenerateHandlesMetacharactersInFilename(): void
    {
        $videoPath = $this->makeVideoFile();

        $videoExtractor = $this->createMock(VideoThumbnailExtractorInterface::class);
        $videoExtractor->method('supports')->willReturn(true);
        $videoExtractor->method('extract')
            ->willReturn(new ExtractedVideoFrame($this->makeJpeg(320, 240)));

        $service = $this->service($videoExtractor);

        $thumbnail = $service->generate($videoPath);

        $this->assertNotNull($thumbnail);
        $this->assertFileExists($this->storageDir.'/'.$thumbnail);
    }
}
