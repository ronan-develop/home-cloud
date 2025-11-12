<?php

namespace App\Tests\Unit;

use App\Uploader\UploaderFactory;
use App\Uploader\UploaderInterface;
use PHPUnit\Framework\TestCase;


use Symfony\Component\HttpFoundation\File\UploadedFile;

class DummyUploader implements UploaderInterface
{
    public function supports(UploadedFile $file, array $context = []): bool
    {
        return $file->getClientOriginalName() === 'dummy.txt';
    }
    public function upload(UploadedFile $file, array $context = []): string
    {
        return 'dummy-uploaded';
    }
    public function getTargetDirectory(): string
    {
        return '/tmp';
    }
}

class FailingUploader implements UploaderInterface
{
    public function supports(UploadedFile $file, array $context = []): bool
    {
        return false;
    }
    public function upload(UploadedFile $file, array $context = []): string
    {
        throw new \LogicException('Should not be called');
    }
    public function getTargetDirectory(): string
    {
        return '/tmp';
    }
}

class UploaderFactoryTest extends TestCase
{
    public function testReturnsCorrectUploader()
    {
        $dummy = new DummyUploader();
        $factory = new UploaderFactory([
            $dummy,
            new FailingUploader(),
        ]);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn('dummy.txt');
        $uploader = $factory->getUploader($file);
        $this->assertSame($dummy, $uploader);
        $this->assertEquals('dummy-uploaded', $uploader->upload($file));
    }

    public function testThrowsIfNoUploaderSupportsFile()
    {
        $factory = new UploaderFactory([
            new FailingUploader(),
        ]);
        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalName')->willReturn('unknown.txt');
        $this->expectException(\InvalidArgumentException::class);
        $factory->getUploader($file);
    }
}
