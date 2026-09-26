<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Takeout\TakeoutMetadataReader;
use PHPUnit\Framework\TestCase;

/**
 * #327 — parse un fichier <nom>.supplemental-metadata.json d'un export
 * Google Takeout. Jamais bloquant : absence ou malformation renvoie des
 * valeurs nulles, l'EXIF embarqué reste le filet de sécurité (cf.
 * MediaDateResolver).
 */
final class TakeoutMetadataReaderTest extends TestCase
{
    private TakeoutMetadataReader $reader;

    protected function setUp(): void
    {
        $this->reader = new TakeoutMetadataReader();
    }

    private function writeJson(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'takeout_meta_') . '.json';
        file_put_contents($path, $content);

        return $path;
    }

    public function testReadsValidMetadataWithDateAndGeo(): void
    {
        $path = $this->writeJson(json_encode([
            'title' => 'IMG_1234.jpg',
            'photoTakenTime' => ['timestamp' => '1615999999', 'formatted' => '17 mars 2021'],
            'geoData' => ['latitude' => 48.8566, 'longitude' => 2.3522, 'altitude' => 0.0],
        ]));

        $metadata = $this->reader->read($path);
        @unlink($path);

        $this->assertNotNull($metadata);
        $this->assertEquals(new \DateTimeImmutable('@1615999999'), $metadata->takenAt);
        $this->assertSame(48.8566, $metadata->latitude);
        $this->assertSame(2.3522, $metadata->longitude);
    }

    public function testTreatsZeroZeroGeoDataAsAbsent(): void
    {
        // Google encode l'absence de géolocalisation par latitude=0,
        // longitude=0 (pas null) — piège documenté du format Takeout : (0,0)
        // est un point réel dans le golfe de Guinée, jamais une vraie photo.
        $path = $this->writeJson(json_encode([
            'title' => 'IMG_1234.jpg',
            'photoTakenTime' => ['timestamp' => '1615999999'],
            'geoData' => ['latitude' => 0.0, 'longitude' => 0.0, 'altitude' => 0.0],
        ]));

        $metadata = $this->reader->read($path);
        @unlink($path);

        $this->assertNotNull($metadata);
        $this->assertNull($metadata->latitude);
        $this->assertNull($metadata->longitude);
    }

    public function testReturnsNullWhenFileDoesNotExist(): void
    {
        $metadata = $this->reader->read('/tmp/does-not-exist-' . uniqid() . '.json');

        $this->assertNull($metadata);
    }

    public function testReturnsNullWhenJsonIsMalformed(): void
    {
        $path = $this->writeJson('{not valid json');

        $metadata = $this->reader->read($path);
        @unlink($path);

        $this->assertNull($metadata);
    }

    public function testReturnsNullDateWhenPhotoTakenTimeMissing(): void
    {
        $path = $this->writeJson(json_encode(['title' => 'IMG_1234.jpg']));

        $metadata = $this->reader->read($path);
        @unlink($path);

        $this->assertNotNull($metadata);
        $this->assertNull($metadata->takenAt);
        $this->assertNull($metadata->latitude);
        $this->assertNull($metadata->longitude);
    }
}
