<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ThumbnailService;
use PHPUnit\Framework\TestCase;
use RonanLenouvel\RawPreviewExtractor\Orientation;
use RonanLenouvel\RawPreviewExtractor\RawPreviewExtractor;

/**
 * Validation sur un vrai fichier RAW, là où ThumbnailServiceRawTest double
 * l'extracteur et ne teste que le branchement.
 *
 * La fixture n'est pas versionnée : un NEF pèse plus de 50 Mo. Les tests se
 * skippent donc en CI et sur toute machine qui ne l'a pas — ils servent à
 * valider en local, pas à protéger contre les régressions.
 */
final class ThumbnailServiceRealRawTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../fixtures/demo-photos/DSC_0190 1.NEF';

    private string $storageDir;

    protected function setUp(): void
    {
        if (!file_exists(self::FIXTURE)) {
            $this->markTestSkipped('Fixture NEF absente (non versionnée, > 50 Mo).');
        }

        $this->storageDir = sys_get_temp_dir() . '/hc-real-raw-' . uniqid();
        mkdir($this->storageDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $thumbsDir = ($this->storageDir ?? '') . '/thumbs';
        if (is_dir($thumbsDir)) {
            foreach (glob($thumbsDir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($thumbsDir);
        }
        if (isset($this->storageDir) && is_dir($this->storageDir)) {
            rmdir($this->storageDir);
        }
    }

    public function testExtractsFullResolutionPreviewFromRealNef(): void
    {
        $extractor = RawPreviewExtractor::createDefault();

        $this->assertTrue($extractor->supports(self::FIXTURE));

        $preview = $extractor->extract(self::FIXTURE);

        // Le D850 embarque une preview pleine résolution — pas une vignette
        // 160x120 comme certains boîtiers plus anciens.
        $this->assertSame(8256, $preview->width);
        $this->assertSame(5504, $preview->height);
        $this->assertSame(Orientation::Rotate90, $preview->orientation);

        // Validation croisée : GD doit savoir relire ce que le package a extrait.
        $gd = getimagesizefromstring($preview->jpegData);
        $this->assertNotFalse($gd, 'La preview extraite doit être un JPEG décodable');
        $this->assertSame('image/jpeg', $gd['mime']);
    }

    public function testGeneratesUprightThumbnailFromRealNef(): void
    {
        $service = new ThumbnailService(
            $this->storageDir,
            RawPreviewExtractor::createDefault(),
        );

        $thumb = $service->generate(self::FIXTURE);

        $this->assertNotNull($thumb, 'Un NEF réel doit produire une vignette');

        $size = getimagesize($this->storageDir . '/' . $thumb);
        $this->assertNotFalse($size);

        // La preview est un paysage 8256x5504 en Rotate90 : redressée, elle
        // devient un portrait. Redimensionner avant de tourner donnerait un
        // 213x320 — droit, mais plus petit que les photos paysage.
        $this->assertSame(320, $size[0]);
        $this->assertSame(480, $size[1]);
    }
}
