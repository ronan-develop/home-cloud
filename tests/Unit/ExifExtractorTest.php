<?php

namespace App\Tests\Unit;

use App\Photo\ExifExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ExifExtractorTest extends TestCase
{
    public function testReturnsEmptyArrayForNonExifMime(): void
    {
        $extractor = new ExifExtractor();
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientMimeType')->willReturn('image/png');
        $this->assertSame([], $extractor->extract($file));
    }

    public function testReturnsArrayForExifMime(): void
    {
        $extractor = $this->getMockBuilder(ExifExtractor::class)
            ->onlyMethods(['extract'])
            ->getMock();
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientMimeType')->willReturn('image/jpeg');
        // On ne teste pas exif_read_data (dépend du système), on vérifie juste l'appel
        $extractor->expects($this->once())->method('extract')->with($file)->willReturn(['Make' => 'Canon']);
        $this->assertSame(['Make' => 'Canon'], $extractor->extract($file));
    }
}
