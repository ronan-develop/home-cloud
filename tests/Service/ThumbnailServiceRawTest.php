<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ThumbnailService;
use PHPUnit\Framework\TestCase;
use RonanLenouvel\RawPreviewExtractor\Exception\PreviewNotFoundException;
use RonanLenouvel\RawPreviewExtractor\ExtractedPreview;
use RonanLenouvel\RawPreviewExtractor\Format\Format;
use RonanLenouvel\RawPreviewExtractor\Orientation;
use RonanLenouvel\RawPreviewExtractor\RawPreviewExtractorInterface;

/**
 * GD ne sait pas décoder un RAW : imagecreatefromstring() échouait
 * silencieusement et le média restait sans vignette. On extrait désormais la
 * preview JPEG embarquée par l'appareil et on la passe au pipeline GD existant.
 *
 * Les fixtures sont des JPEG (pas de vrais RAW, trop lourds pour le repo) :
 * l'extracteur est doublé, c'est le branchement dans ThumbnailService qui est
 * testé ici, pas le parsing RAW — déjà couvert par le package lui-même.
 */
final class ThumbnailServiceRawTest extends TestCase
{
    private string $storageDir;

    protected function setUp(): void
    {
        $this->storageDir = sys_get_temp_dir() . '/hc-thumb-raw-' . uniqid();
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

    /**
     * Fabrique un JPEG réel de la taille demandée, utilisable comme preview.
     */
    private function makeJpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($image);
        $data = (string) ob_get_clean();
        imagedestroy($image);

        return $data;
    }

    /**
     * Un faux fichier RAW : le contenu importe peu, l'extracteur est doublé.
     */
    private function makeRawFile(): string
    {
        $path = $this->storageDir . '/photo.nef';
        file_put_contents($path, 'not-a-real-raw-the-extractor-is-a-double');

        return $path;
    }

    public function testGeneratesThumbnailFromEmbeddedPreviewWhenFileIsRaw(): void
    {
        $rawPath = $this->makeRawFile();

        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->expects($this->once())->method('supports')->with($rawPath)->willReturn(true);
        $extractor->expects($this->once())->method('extract')->with($rawPath)->willReturn(
            new ExtractedPreview($this->makeJpeg(800, 600), 800, 600, Format::NEF),
        );

        $service = new ThumbnailService($this->storageDir, $extractor);
        $thumb = $service->generate($rawPath);

        $this->assertNotNull($thumb, 'Un RAW avec preview embarquée doit produire une vignette');
        $this->assertStringStartsWith('thumbs/', $thumb);
        $this->assertFileExists($this->storageDir . '/' . $thumb);
    }

    public function testReturnsNullWhenRawHasNoEmbeddedPreview(): void
    {
        $rawPath = $this->makeRawFile();

        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->method('supports')->willReturn(true);
        $extractor->method('extract')->willThrowException(new PreviewNotFoundException('no preview'));

        $service = new ThumbnailService($this->storageDir, $extractor);

        // Dégradation gracieuse : pas de vignette, mais pas d'exception non plus.
        $this->assertNull($service->generate($rawPath));
    }

    public function testRotatesThumbnailAccordingToPreviewOrientation(): void
    {
        $rawPath = $this->makeRawFile();

        // Proportions d'une preview NEF réelle (3:2), stockée couchée parce que
        // l'appareil était tenu à la verticale : la vignette doit ressortir en
        // portrait. L'assertion est dimensionnelle, pas visuelle — elle échoue
        // franchement sans rotation, et un imagerotate() du mauvais signe
        // (180° au lieu de 90°) ne la trompe pas non plus.
        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->method('supports')->willReturn(true);
        $extractor->method('extract')->willReturn(
            new ExtractedPreview($this->makeJpeg(900, 600), 900, 600, Format::NEF, Orientation::Rotate90),
        );

        $service = new ThumbnailService($this->storageDir, $extractor);
        $thumb = $service->generate($rawPath);

        $this->assertNotNull($thumb);
        $size = getimagesize($this->storageDir . '/' . $thumb);
        $this->assertNotFalse($size);

        [$width, $height] = $size;
        $this->assertGreaterThan($width, $height, 'Une preview Rotate90 doit ressortir en portrait');
        // Redresser après avoir redimensionné donnerait 213px de large ici :
        // la vignette serait droite, mais plus petite que les photos paysage.
        $this->assertSame(320, $width, 'La largeur cible se calcule sur l\'image redressée');
        $this->assertSame(480, $height, 'Le ratio 3:2 doit être conservé après rotation');
    }

    public function testFallsBackToGdForRegularImages(): void
    {
        // Un JPEG normal ne passe pas par l'extracteur : supports() renvoie false.
        $jpegPath = $this->storageDir . '/photo.jpg';
        file_put_contents($jpegPath, $this->makeJpeg(400, 300));

        $extractor = $this->createMock(RawPreviewExtractorInterface::class);
        $extractor->method('supports')->willReturn(false);
        $extractor->expects($this->never())->method('extract');

        $service = new ThumbnailService($this->storageDir, $extractor);
        $thumb = $service->generate($jpegPath);

        $this->assertNotNull($thumb, 'Le pipeline GD existant doit continuer à fonctionner');
    }
}
