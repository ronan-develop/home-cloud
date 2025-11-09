<?php

namespace App\Tests\Unit;

use App\Form\Handler\PhotoUploadHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormInterface;

class PhotoUploadHandlerTest extends TestCase
{
    public function testExtractFormDataReturnsExpectedArray(): void
    {
        $form = $this->createMock(FormInterface::class);
        $uploadedFile = $this->createMock(UploadedFile::class);
        $form->method('get')->willReturnMap([
            ['photo', $uploadedFile],
            ['title', 'Titre'],
            ['description', 'Desc'],
            ['tags', ['tag1', 'tag2']],
        ]);

        $handler = $this->getHandler();
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('extractFormData');
        $method->setAccessible(true);
        $result = $method->invoke($handler, $form);

        $this->assertEquals([
            'photo' => $uploadedFile,
            'title' => 'Titre',
            'description' => 'Desc',
            'tags' => ['tag1', 'tag2'],
        ], $result);
    }

    private function getHandler(): PhotoUploadHandler
    {
        return new PhotoUploadHandler(
            $this->createMock(\Symfony\Component\Form\FormFactoryInterface::class),
            $this->createMock(\App\Service\PhotoUploader::class),
            $this->createMock(\Doctrine\ORM\EntityManagerInterface::class),
            $this->createMock(\App\Form\Validator\UploadedFilePresenceValidator::class)
        );
    }
}
