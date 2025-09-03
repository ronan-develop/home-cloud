<?php

namespace App\EventListener;

use App\Tenant\TenantResolver;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Psr\Log\LoggerInterface;

class TenantRequestListener
{
    public function __construct(private TenantResolver $resolver, private LoggerInterface $logger) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // ignore sub-requests
        if (!$event->isMainRequest()) {
            return;
        }

        $host = $request->getHost();

        $tenant = $this->resolver->resolveFromHost($host);

        if ($tenant) {
            $request->attributes->set('tenant', $tenant);
        } else {
            // pour visibilité en dev / logs
            $this->logger->debug('Tenant not found for host', ['host' => $host]);
            // Optionnel: vous pouvez décider de lancer une exception si le host est invalide
        }
    }
}
