<?php

namespace App\Tests\Unit;

use App\Photo\PhotoMimeTypeValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoMimeTypeValidatorTest extends TestCase
{
    public function testStaticAcceptsValidMimeTypes(): void
    {
        $allowed = ['image/jpeg', 'image/png'];
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientMimeType')->willReturn('image/png');
        $this->expectNotToPerformAssertions();
        PhotoMimeTypeValidator::validateStatic($file, $allowed);
    }

    public function testStaticRejectsInvalidMimeTypes(): void
    {
        $allowed = ['image/jpeg', 'image/png'];
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientMimeType')->willReturn('application/pdf');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Type MIME refusé : 'application/pdf'. Types acceptés : image/jpeg, image/png"
        );
        PhotoMimeTypeValidator::validateStatic($file, $allowed);
    }

    public function testAcceptsValidMimeTypes(): void
    {
        $allowed = ['image/jpeg', 'image/png'];
        $validator = new PhotoMimeTypeValidator($allowed);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientMimeType')->willReturn('image/jpeg');
        $this->expectNotToPerformAssertions();
        $validator->validate($file);
    }

    public function testRejectsInvalidMimeTypes(): void
    {
        $allowed = ['image/jpeg', 'image/png'];
        $validator = new PhotoMimeTypeValidator($allowed);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientMimeType')->willReturn('application/pdf');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Type MIME refusé : 'application/pdf'. Types acceptés : image/jpeg, image/png"
        );
        $validator->validate($file);
    }
}
