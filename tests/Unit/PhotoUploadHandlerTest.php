<?php

namespace App\Tests\Unit;

use App\Form\Handler\PhotoUploadHandler;
use PHPUnit\Framework\TestCase;
use App\Form\Dto\PhotoUploadData;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormInterface;

class PhotoUploadHandlerTest extends TestCase
{
    public function testExtractFormDataReturnsExpectedArray(): void
    {
        $form = $this->createMock(FormInterface::class);
        $titleField = $this->createMock(FormInterface::class);
        $descField = $this->createMock(FormInterface::class);
        $favField = $this->createMock(FormInterface::class);
        $titleField->method('getData')->willReturn('Titre');
        $descField->method('getData')->willReturn('Desc');
        $favField->method('getData')->willReturn(true);
        $form->method('get')->willReturnMap([
            ['title', $titleField],
            ['description', $descField],
            ['isFavorite', $favField],
        ]);

        $handler = $this->getHandler();
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('extractFormData');
        $method->setAccessible(true);
        $result = $method->invoke($handler, $form);

        $this->assertInstanceOf(PhotoUploadData::class, $result);
        $this->assertSame('Titre', $result->title);
        $this->assertSame('Desc', $result->description);
        $this->assertTrue($result->isFavorite);
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
