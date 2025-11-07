<?php

namespace App\Tests\Unit\Service;

use App\Service\ErrorHandler;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class ErrorHandlerTest extends TestCase
{
    private $logger;
    private $twig;
    private $errorHandler;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->twig = $this->createMock(Environment::class);
        $this->errorHandler = new ErrorHandler($this->logger, $this->twig);
    }

    public function testHandleLogsErrorAndRendersTemplate(): void
    {
        $exception = new \Exception('Erreur de test');
        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('Erreur de test'),
                $this->arrayHasKey('exception')
            );
        $this->twig->expects($this->once())
            ->method('render')
            ->with(
                $this->equalTo('error/generic.html.twig'),
                $this->arrayHasKey('error_message')
            )
            ->willReturn('<html>Erreur</html>');
        $response = $this->errorHandler->handle($exception);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('Erreur', $response->getContent());
    }
}
