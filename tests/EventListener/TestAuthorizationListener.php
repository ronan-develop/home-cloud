<?php

namespace App\Tests\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class TestAuthorizationListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        // Copier HTTP_AUTHORIZATION vers Authorization header si manquant
        if ($request->server->has('HTTP_AUTHORIZATION') && !$request->headers->has('Authorization')) {
            $authHeader = $request->server->get('HTTP_AUTHORIZATION');
            $request->headers->set('Authorization', $authHeader);
        }
    }
}
