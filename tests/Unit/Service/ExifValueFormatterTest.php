<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ExifValueFormatter;
use PHPUnit\Framework\TestCase;

/**
 * exif_read_data expose les réglages sous forme brute (rationnels "num/denom",
 * chaînes) : ce formateur les rend lisibles pour un photographe, sans dépendre
 * de l'extension EXIF (pur, donc testable isolément).
 */
final class ExifValueFormatterTest extends TestCase
{
    private ExifValueFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ExifValueFormatter();
    }

    public function testFNumberFromRational(): void
    {
        self::assertSame('2.8', $this->formatter->fNumber('28/10'));
        self::assertSame('4', $this->formatter->fNumber('4/1'));
        self::assertSame('5.6', $this->formatter->fNumber('56/10'));
    }

    public function testFNumberHandlesPlainDecimal(): void
    {
        self::assertSame('2.8', $this->formatter->fNumber('2.8'));
    }

    public function testFNumberNullWhenEmptyOrZeroDenominator(): void
    {
        self::assertNull($this->formatter->fNumber(null));
        self::assertNull($this->formatter->fNumber(''));
        self::assertNull($this->formatter->fNumber('28/0'));
    }

    public function testExposureKeepsSubSecondFraction(): void
    {
        self::assertSame('1/250', $this->formatter->exposure('1/250'));
        // Rationnel non réduit → réduit à 1/250.
        self::assertSame('1/250', $this->formatter->exposure('10/2500'));
    }

    public function testExposureAtOrAboveOneSecondIsDecimal(): void
    {
        self::assertSame('2', $this->formatter->exposure('2/1'));
        self::assertSame('1.3', $this->formatter->exposure('13/10'));
    }

    public function testExposureNull(): void
    {
        self::assertNull($this->formatter->exposure(null));
        self::assertNull($this->formatter->exposure('0/0'));
    }

    public function testFocalLengthFromRational(): void
    {
        self::assertSame('50', $this->formatter->focalLength('50/1'));
        self::assertSame('35', $this->formatter->focalLength('350/10'));
        self::assertSame('24.5', $this->formatter->focalLength('245/10'));
    }

    public function testFocalLengthNull(): void
    {
        self::assertNull($this->formatter->focalLength(null));
        self::assertNull($this->formatter->focalLength('50/0'));
    }
}
