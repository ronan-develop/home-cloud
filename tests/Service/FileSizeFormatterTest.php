<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\FileSizeFormatter;
use PHPUnit\Framework\TestCase;

final class FileSizeFormatterTest extends TestCase
{
    private FileSizeFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new FileSizeFormatter();
    }

    public function testFormatZeroBytes(): void
    {
        $this->assertSame('0 o', $this->formatter->format(0));
    }

    public function testFormatBytesBelowOneKilobyte(): void
    {
        $this->assertSame('512 o', $this->formatter->format(512));
    }

    public function testFormatExactKilobyte(): void
    {
        $this->assertSame('1 Ko', $this->formatter->format(1024));
    }

    public function testFormatKilobytesWithDecimal(): void
    {
        $this->assertSame('1,5 Ko', $this->formatter->format(1536));
    }

    public function testFormatMegabytes(): void
    {
        $this->assertSame('2 Mo', $this->formatter->format(2 * 1024 * 1024));
    }

    public function testFormatGigabytes(): void
    {
        $this->assertSame('1,25 Go', $this->formatter->format((int) (1.25 * 1024 * 1024 * 1024)));
    }

    public function testFormatTerabytes(): void
    {
        $this->assertSame('3 To', $this->formatter->format(3 * 1024 * 1024 * 1024 * 1024));
    }
}
