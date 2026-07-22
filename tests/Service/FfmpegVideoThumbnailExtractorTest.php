<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\Video\FfmpegVideoThumbnailExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;

class FfmpegVideoThumbnailExtractorTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../../fixtures/demo-videos/testsrc.mp4';

    private ?string $storageDir = null;

    protected function setUp(): void
    {
        $ffmpeg = (new ExecutableFinder())->find('ffmpeg');
        if ($ffmpeg === null) {
            $this->markTestSkipped('ffmpeg absent de cette machine (présent en CI).');
        }

        $this->storageDir = sys_get_temp_dir().'/hc-test-video-extract-'.uniqid();
        mkdir($this->storageDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->storageDir) && is_dir($this->storageDir)) {
            array_map('unlink', glob($this->storageDir.'/*') ?: []);
            rmdir($this->storageDir);
        }
    }

    public function testSupportsCommonVideoFormats(): void
    {
        $extractor = FfmpegVideoThumbnailExtractor::createDefault(
            (new ExecutableFinder())->find('ffmpeg') ?: 'ffmpeg',
            (new ExecutableFinder())->find('ffprobe') ?: 'ffprobe',
        );

        $this->assertTrue($extractor->supports('video.mp4'));
        $this->assertTrue($extractor->supports('video.webm'));
        $this->assertTrue($extractor->supports('video.mov'));
        $this->assertFalse($extractor->supports('image.jpg'));
        $this->assertFalse($extractor->supports('photo.nef'));
    }

    public function testExtractsFrameFromFixtureWhenAvailable(): void
    {
        if (!file_exists(self::FIXTURE)) {
            $this->markTestSkipped('Fixture vidéo absent (testsrc.mp4).');
        }

        $ffmpeg = (new ExecutableFinder())->find('ffmpeg');
        if ($ffmpeg === null) {
            $this->markTestSkipped('ffmpeg absent.');
        }

        $extractor = FfmpegVideoThumbnailExtractor::createDefault(
            $ffmpeg,
            (new ExecutableFinder())->find('ffprobe') ?: 'ffprobe',
        );

        $frame = $extractor->extract(self::FIXTURE);

        $this->assertNotEmpty($frame->jpegData);
        $image = @imagecreatefromstring($frame->jpegData);
        $this->assertNotFalse($image, 'Frame doit être un JPEG valide');
        imagedestroy($image);
    }
}
