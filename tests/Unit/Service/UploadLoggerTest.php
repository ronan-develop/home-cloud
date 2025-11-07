<?php

namespace App\Tests\Unit\Service;

use App\Service\UploadLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Entity\User;

class UploadLoggerTest extends TestCase
{
    private $logger;
    private $uploadLogger;
    private $user;
    private $file;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->uploadLogger = new UploadLogger($this->logger);
        $this->user = $this->createMock(User::class);
        $this->user->method('getUserIdentifier')->willReturn('testuser');
        $this->file = $this->createMock(UploadedFile::class);
        $this->file->method('getClientOriginalName')->willReturn('test.txt');
        $this->file->method('getSize')->willReturn(1234);
        $this->file->method('getMimeType')->willReturn('text/plain');
    }

    public function testLogSuccess(): void
    {
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->equalTo('Upload réussi'),
                $this->arrayHasKey('filename')
            );
        $this->uploadLogger->logSuccess($this->file, $this->user);
    }

    public function testLogError(): void
    {
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('Erreur upload'),
                $this->arrayHasKey('error')
            );
        $this->uploadLogger->logError($this->file, $this->user, 'Erreur de test');
    }

    public function testLogValidation(): void
    {
        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                $this->equalTo('Validation upload'),
                $this->arrayHasKey('validation')
            );
        $this->uploadLogger->logValidation($this->file, $this->user, 'Validation OK');
    }
}
