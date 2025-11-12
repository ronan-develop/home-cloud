<?php

namespace App\Tests\Unit;

use App\Entity\Photo;
use App\Entity\User;
<<<<<<< HEAD
use App\Uploader\PhotoUploader;
=======
>>>>>>> origin/feat/albums
use App\Form\Dto\PhotoUploadData;
use App\Photo\PhotoMimeTypeValidator;
use App\Photo\ExifExtractor;
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

        $user = $this->createMock(User::class);
        $data = new PhotoUploadData('Titre', 'Desc', true);

        /** @var \App\Uploader\UploadDirectoryManager&\PHPUnit\Framework\MockObject\MockObject $directoryManager */
        $directoryManager = $this->createMock(\App\Uploader\UploadDirectoryManager::class);
        $directoryManager->expects($this->once())->method('ensureDirectoryExists')->with($targetDir);

        /** @var \App\Uploader\SafeFileMover&\PHPUnit\Framework\MockObject\MockObject $fileMover */
        $fileMover = $this->createMock(\App\Uploader\SafeFileMover::class);
        $fileMover->expects($this->once())->method('move')->with($file, $targetDir, $this->anything());

<<<<<<< HEAD

        $fileNameGenerator = $this->createMock(\App\Uploader\FileNameGeneratorInterface::class);
=======
        $fileNameGenerator = $this->createMock(\App\Interface\FileNameGeneratorInterface::class);
>>>>>>> origin/feat/albums
        $fileNameGenerator->expects($this->once())
            ->method('generate')
            ->with('test.jpg')
            ->willReturn('unique_test.jpg');

        $uploader = new \App\Uploader\PhotoUploader(
            $targetDir,
            $extractor,
            $validator,
            $directoryManager,
            $fileMover,
            $fileNameGenerator
        );
        $photo = $uploader->uploadPhoto($file, $user, $data);

        $this->assertInstanceOf(Photo::class, $photo);
        $this->assertSame('Titre', $photo->getTitle());
        $this->assertSame('Desc', $photo->getDescription());
        $this->assertTrue($photo->isFavorite());
        $this->assertSame($user, $photo->getUser());
        $this->assertSame($exif, $photo->getExifData());
        $this->assertSame('image/jpeg', $photo->getMimeType());
        $this->assertSame(1234, $photo->getSize());
        $this->assertSame('unique_test.jpg', $photo->getFilename());
        $this->assertNotNull($photo->getHash());
        $this->assertNotNull($photo->getUploadedAt());
    }
}
