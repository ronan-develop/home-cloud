<?php

namespace App\Tests\Unit;

use App\Service\PhotoMimeTypeValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoMimeTypeValidatorTest extends TestCase
{
    public function testAcceptsValidMimeTypes(): void
    {
        $validator = new PhotoMimeTypeValidator();
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientMimeType')->willReturn('image/jpeg');
        $this->expectNotToPerformAssertions();
        $validator->validate($file);
    }

    public function testRejectsInvalidMimeTypes(): void
    {
        $validator = new PhotoMimeTypeValidator();
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientMimeType')->willReturn('application/pdf');
        $this->expectException(\InvalidArgumentException::class);
        $validator->validate($file);
    }
}
