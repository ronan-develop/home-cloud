<?php

namespace App\Tests\Unit;

use App\Entity\Photo;
use App\Entity\User;
use App\Service\PhotoUploader;
use App\Service\PhotoMimeTypeValidator;
use App\Service\ExifExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoUploaderTest extends TestCase
{
    public function testUploadPhotoSetsAllFields(): void
    {
        $targetDir = sys_get_temp_dir();
        $validator = $this->createMock(PhotoMimeTypeValidator::class);
        $validator->expects($this->once())->method('validate');
        $exif = ['Make' => 'Canon'];
        $extractor = $this->createMock(ExifExtractor::class);
        $extractor->method('extract')->willReturn($exif);

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientMimeType')->willReturn('image/jpeg');
        $file->method('getClientOriginalName')->willReturn('test.jpg');
        $file->method('getSize')->willReturn(1234);
        $file->method('getPathname')->willReturn(__FILE__);
        $file->expects($this->once())->method('move');

        $user = $this->createMock(User::class);
        $data = ['title' => 'Titre', 'description' => 'Desc', 'isFavorite' => true];

        $uploader = new PhotoUploader($targetDir, $validator, $extractor);
        $photo = $uploader->uploadPhoto($file, $user, $data);

        $this->assertInstanceOf(Photo::class, $photo);
        $this->assertSame('Titre', $photo->getTitle());
        $this->assertSame('Desc', $photo->getDescription());
        $this->assertTrue($photo->isFavorite());
        $this->assertSame($user, $photo->getUser());
        $this->assertSame($exif, $photo->getExifData());
        $this->assertSame('image/jpeg', $photo->getMimeType());
        $this->assertSame(1234, $photo->getSize());
        $this->assertNotNull($photo->getFilename());
        $this->assertNotNull($photo->getHash());
        $this->assertNotNull($photo->getUploadedAt());
    }
}
