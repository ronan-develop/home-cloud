<?php

namespace App\EventListener;

use App\Exception\NoFilesFoundException;
use App\Service\FileErrorRedirectorService;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class FileExceptionListener
{
    private FileErrorRedirectorService $errorRedirector;

    public function __construct(FileErrorRedirectorService $errorRedirector)
    {
        $this->errorRedirector = $errorRedirector;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if ($exception instanceof NoFilesFoundException) {
            $response = $this->errorRedirector->handle($exception->getMessage(), 'app_home');
            $event->setResponse($response);
        }
        // Ajoute ici d'autres exceptions métier si besoin
    }
}
