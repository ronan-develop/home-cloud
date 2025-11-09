<?php

namespace App\Tests\Unit;

use App\Form\Handler\PhotoUploadHandler;
use PHPUnit\Framework\TestCase;
use App\Form\Dto\PhotoUploadData;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormInterface;

class PhotoUploadHandlerTest extends TestCase
{
    public function testHandleRefusesUploadIfUserHasNoRoleUser(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $formFactory = $this->createMock(\Symfony\Component\Form\FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $photoUploader = $this->createMock(\App\Service\PhotoUploader::class);
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $filePresenceValidator = $this->createMock(\App\Form\Validator\UploadedFilePresenceValidator::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $handler = new \App\Form\Handler\PhotoUploadHandler(
            $formFactory,
            $photoUploader,
            $em,
            $filePresenceValidator,
            $logger
        );

        $request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $user->method('getRoles')->willReturn([]);

        $result = $handler->handle($request, $user);
        $this->assertFalse($result->success);
        $this->assertStringContainsString('droit', strtolower($result->errorMessage));
    }
    public function testHandleLogsCriticalExceptionAndReturnsGenericError(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isSubmitted')->willReturn(true);
        $form->method('isValid')->willReturn(true);
        $form->method('get')->willReturn($this->createMock(FormInterface::class));

        $formFactory = $this->createMock(\Symfony\Component\Form\FormFactoryInterface::class);
        $formFactory->method('create')->willReturn($form);

        $photoUploader = $this->createMock(\App\Service\PhotoUploader::class);
        $photoUploader->method('uploadPhoto')->willThrowException(new \RuntimeException('Erreur technique !'));

        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $filePresenceValidator = $this->createMock(\App\Form\Validator\UploadedFilePresenceValidator::class);
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('Erreur critique'),
                $this->arrayHasKey('exception')
            );

        $handler = new \App\Form\Handler\PhotoUploadHandler(
            $formFactory,
            $photoUploader,
            $em,
            $filePresenceValidator,
            $logger
        );

        $request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
        $user = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user1');
        $user->method('getRoles')->willReturn(['ROLE_USER']);
        $request->method('getRequestUri')->willReturn('/photos/upload');

        $result = $handler->handle($request, $user);
        $this->assertFalse($result->success);
        $this->assertStringContainsString('erreur technique', strtolower($result->errorMessage));
    }
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
            $this->createMock(\App\Form\Validator\UploadedFilePresenceValidator::class),
            $this->createMock(\Psr\Log\LoggerInterface::class)
        );
    }
}
