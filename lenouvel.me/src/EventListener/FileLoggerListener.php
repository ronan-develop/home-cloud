<?php

namespace App\EventListener;

use App\Entity\File;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: 'kernel.request', priority: 0)]
class FileLoggerListener
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(RequestEvent $event): void
    {
        File::setLogger($this->logger);
    }
}
