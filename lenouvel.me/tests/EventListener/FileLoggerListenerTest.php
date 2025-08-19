<?php

namespace App\Tests\EventListener;

use App\Entity\File;
use App\EventListener\FileLoggerListener;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class FileLoggerListenerTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        File::setLogger(null); // Nettoyage de l'état statique
    }

    public function testLoggerIsInjectedAndUsedOnViolation(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('[SECURITY]'),
                $this->arrayHasKey('filename')
            );

        $listener = new FileLoggerListener($logger);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $listener($event);

        // Vérification explicite que le logger est bien injecté
        $this->assertNotNull(File::getLogger(), 'Le logger doit être injecté dans File');

        $file = new File();
        $file->setName('con.pdf'); // nom réservé
        $file->setPath('dossier');
        $file->setMimeType('application/pdf');
        $file->setSize(1024);
        // Ajout d'un owner factice pour éviter le TypeError
        $userClass = \App\Entity\User::class;
        $owner = $this->getMockBuilder($userClass)->disableOriginalConstructor()->getMock();
        $owner->method('getId')->willReturn(42);
        $ref = new \ReflectionProperty(File::class, 'owner');
        $ref->setAccessible(true);
        $ref->setValue($file, $owner);
        $context = $this->getMockBuilder('Symfony\Component\Validator\Context\ExecutionContextInterface')->getMock();
        $builder = $this->getMockBuilder('Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface')->getMock();
        $builder->expects($this->any())->method('atPath')->willReturnSelf();
        $builder->expects($this->any())->method('addViolation');
        $context->expects($this->any())->method('buildViolation')->willReturn($builder);
        $file->validateReservedNames($context, null);
    }
}
