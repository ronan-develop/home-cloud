<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Interface\ExifThumbnailExtractorInterface;
use App\Service\ExifThumbnail;
use App\Service\ThumbnailService;
use PHPUnit\Framework\TestCase;
use RonanLenouvel\RawPreviewExtractor\Orientation;
use RonanLenouvel\RawPreviewExtractor\RawPreviewExtractorInterface;

/**
 * Décoder une image pleine résolution avec GD juste pour en tirer une
 * vignette de 320px peut à lui seul saturer la mémoire du worker sur un scan
 * haute résolution (observé en prod : un seul fichier par cycle de cron de
 * 5 min). La plupart des JPEG embarquent déjà une miniature EXIF (IFD1) : on
 * l'utilise en priorité, avec repli sur le décodage GD complet si absente.
 */
final class ThumbnailServiceExifThumbnailTest extends TestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir() . '/hc-thumb-exif-' . uniqid();
        mkdir($this->storageDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $thumbsDir = $this->storageDir . '/thumbs';
        if (is_dir($thumbsDir)) {
            foreach (glob($thumbsDir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($thumbsDir);
        }
        foreach (glob($this->storageDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->storageDir)) {
            rmdir($this->storageDir);
        }
    }

    private function makeJpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);
        $data = (string) ob_get_clean();
        imagedestroy($image);

        return $data;
    }

    private function makePlainFile(): string
    {
        $path = $this->storageDir . '/photo.jpg';
        file_put_contents($path, $this->makeJpeg(2000, 1500));

        return $path;
    }

    private function service(ExifThumbnailExtractorInterface $exifExtractor): ThumbnailService
    {
        $rawExtractor = $this->createMock(RawPreviewExtractorInterface::class);
        $rawExtractor->method('supports')->willReturn(false);

        $videoExtractor = $this->createMock(\App\Interface\VideoThumbnailExtractorInterface::class);
        $videoExtractor->method('supports')->willReturn(false);

        return new ThumbnailService($this->storageDir, $rawExtractor, $exifExtractor, $videoExtractor);
    }

    public function testUsesEmbeddedExifThumbnailWhenPresent(): void
    {
        $path = $this->makePlainFile();

        $exifExtractor = $this->createMock(ExifThumbnailExtractorInterface::class);
        $exifExtractor->expects($this->once())->method('extract')->with($path)->willReturn(
            new ExifThumbnail($this->makeJpeg(160, 120), Orientation::Normal),
        );

        $thumb = $this->service($exifExtractor)->generate($path);

        $this->assertNotNull($thumb, 'Une miniature EXIF présente doit produire une vignette');
        $this->assertFileExists($this->storageDir . '/' . $thumb);
    }

    public function testFallsBackToFullGdDecodeWhenNoExifThumbnail(): void
    {
        $path = $this->makePlainFile();

        $exifExtractor = $this->createMock(ExifThumbnailExtractorInterface::class);
        $exifExtractor->method('extract')->willReturn(null);

        $thumb = $this->service($exifExtractor)->generate($path);

        $this->assertNotNull($thumb, 'Le repli GD complet doit continuer à fonctionner');
    }

    public function testRotatesExifThumbnailAccordingToItsOrientation(): void
    {
        $path = $this->makePlainFile();

        // Miniature stockée couchée (téléphone tenu à la verticale) : la
        // vignette finale doit ressortir en portrait, comme pour les RAW.
        $exifExtractor = $this->createMock(ExifThumbnailExtractorInterface::class);
        $exifExtractor->method('extract')->willReturn(
            new ExifThumbnail($this->makeJpeg(160, 120), Orientation::Rotate90),
        );

        $thumb = $this->service($exifExtractor)->generate($path);

        $this->assertNotNull($thumb);
        $size = getimagesize($this->storageDir . '/' . $thumb);
        $this->assertNotFalse($size);

        [$width, $height] = $size;
        $this->assertGreaterThan($width, $height, 'Une miniature Rotate90 doit ressortir en portrait');
    }
}
