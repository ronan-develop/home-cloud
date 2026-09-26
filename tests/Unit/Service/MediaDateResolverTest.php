<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\MediaDateResolver;
use PHPUnit\Framework\TestCase;

/**
 * #327 — Strategy de résolution de la date de prise de vue entre l'EXIF
 * embarqué et les métadonnées Google Takeout (photoTakenTime.timestamp).
 *
 * Priorité : Google Takeout d'abord (Google peut réencoder l'image et
 * perdre l'EXIF d'origine, la métadonnée Takeout reste alors la seule
 * source fiable de la date réelle de prise de vue), EXIF en repli.
 */
final class MediaDateResolverTest extends TestCase
{
    private MediaDateResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new MediaDateResolver();
    }

    public function testPrefersTakeoutDateWhenBothPresent(): void
    {
        $exifDate = new \DateTimeImmutable('2026-01-01');
        $takeoutDate = new \DateTimeImmutable('2026-03-15');

        $result = $this->resolver->resolve($exifDate, $takeoutDate);

        $this->assertEquals($takeoutDate, $result);
    }

    public function testFallsBackToExifWhenTakeoutDateAbsent(): void
    {
        $exifDate = new \DateTimeImmutable('2026-01-01');

        $result = $this->resolver->resolve($exifDate, null);

        $this->assertEquals($exifDate, $result);
    }

    public function testUsesTakeoutDateWhenExifAbsent(): void
    {
        $takeoutDate = new \DateTimeImmutable('2026-03-15');

        $result = $this->resolver->resolve(null, $takeoutDate);

        $this->assertEquals($takeoutDate, $result);
    }

    public function testReturnsNullWhenBothAbsent(): void
    {
        $result = $this->resolver->resolve(null, null);

        $this->assertNull($result);
    }
}
