<?php

declare(strict_types=1);

namespace App\Tests\Handler;

use App\Entity\File;
use App\Handler\MediaProcessHandler;
use App\Message\MediaProcessMessage;
use App\Interface\MediaProcessorInterface;
use App\Repository\FileRepository;
use PHPUnit\Framework\TestCase;

final class MediaProcessHandlerTest extends TestCase
{
    public function testHandlerDelegatesToMediaProcessorWhenFileExists(): void
    {
        $file = $this->createMock(File::class);

        $fileRepo = $this->createMock(FileRepository::class);
        $fileRepo->method('find')->willReturn($file);

        $processor = $this->createMock(MediaProcessorInterface::class);
        $processor->expects($this->once())->method('process')->with($file);

        $handler = new MediaProcessHandler($fileRepo, $processor);
        $handler(new MediaProcessMessage('some-uuid'));
    }

    public function testHandlerDoesNothingWhenFileNotFound(): void
    {
        $fileRepo = $this->createMock(FileRepository::class);
        $fileRepo->method('find')->willReturn(null);

        $processor = $this->createMock(MediaProcessorInterface::class);
        $processor->expects($this->never())->method('process');

        $handler = new MediaProcessHandler($fileRepo, $processor);
        $handler(new MediaProcessMessage('some-uuid'));
    }
}
