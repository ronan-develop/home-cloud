<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Media;
use App\Entity\User;
use App\Interface\AlbumServiceInterface;
use App\Interface\CreateFileServiceInterface;
use App\Interface\MediaProcessorInterface;
use App\Service\AlbumImportService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AlbumImportServiceTest extends TestCase
{
    private AlbumImportService $service;
    /** @var CreateFileServiceInterface&MockObject */
    private CreateFileServiceInterface $createFileService;
    /** @var MediaProcessorInterface&MockObject */
    private MediaProcessorInterface $mediaProcessor;
    /** @var AlbumServiceInterface&MockObject */
    private AlbumServiceInterface $albumService;

    protected function setUp(): void
    {
        $this->createFileService = $this->createMock(CreateFileServiceInterface::class);
        $this->mediaProcessor    = $this->createMock(MediaProcessorInterface::class);
        $this->albumService      = $this->createMock(AlbumServiceInterface::class);

        $this->service = new AlbumImportService(
            $this->createFileService,
            $this->mediaProcessor,
            $this->albumService,
        );
    }

    private function makeUploadedFile(string $name = 'photo.jpg'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'import_test_');
        file_put_contents($tmp, 'fake-jpeg-bytes');

        return new UploadedFile($tmp, $name, 'image/jpeg', null, true);
    }

    public function testImportUploadsEachFileAndAddsResultingMediaToAlbum(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);

        $file1 = $this->createMock(File::class);
        $file2 = $this->createMock(File::class);

        $folder = new \App\Entity\Folder('Photos', $owner);
        $mediaFile1 = new File('a.jpg', 'image/jpeg', 1, 'a.jpg', $folder, $owner);
        $mediaFile2 = new File('b.jpg', 'image/jpeg', 1, 'b.jpg', $folder, $owner);
        $media1 = new Media($mediaFile1, 'photo');
        $media2 = new Media($mediaFile2, 'photo');

        $this->createFileService
            ->method('createFromUpload')
            ->willReturnOnConsecutiveCalls($file1, $file2);

        $this->mediaProcessor
            ->method('process')
            ->willReturnOnConsecutiveCalls($media1, $media2);

        $this->albumService
            ->expects($this->once())
            ->method('addMedias')
            ->with($album, [$media1->getId()->toRfc4122(), $media2->getId()->toRfc4122()], $owner);

        $this->service->import($album, [$this->makeUploadedFile(), $this->makeUploadedFile()], $owner);
    }

    public function testImportSkipsFilesThatDoNotProduceAMedia(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);

        $file = $this->createMock(File::class);
        $this->createFileService->method('createFromUpload')->willReturn($file);
        $this->mediaProcessor->method('process')->willReturn(null);

        $this->albumService
            ->expects($this->once())
            ->method('addMedias')
            ->with($album, [], $owner);

        $this->service->import($album, [$this->makeUploadedFile()], $owner);
    }

    public function testImportWithNoFilesDoesNotCallAddMedias(): void
    {
        $owner = new User('test@example.com', 'Test');
        $album = new Album('Vacances', $owner);

        $this->createFileService->expects($this->never())->method('createFromUpload');
        $this->albumService->expects($this->never())->method('addMedias');

        $this->service->import($album, [], $owner);
    }
}
