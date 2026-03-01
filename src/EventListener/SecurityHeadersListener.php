<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute les headers de sécurité HTTP sur toutes les réponses de l'API.
 *
 * Headers ajoutés sur toutes les réponses :
 * - X-Content-Type-Options: nosniff  → empêche le MIME sniffing navigateur
 * - X-Frame-Options: DENY            → empêche l'embedding dans des iframes (clickjacking)
 * - Referrer-Policy: no-referrer     → ne transmet pas l'URL de la page précédente
 *
 * Header ajouté uniquement sur les routes /api/* (hors /api/docs) :
 * - Content-Security-Policy: default-src 'none' → l'API ne sert que du JSON
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class SecurityHeadersListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'no-referrer');

        $path = $event->getRequest()->getPathInfo();

        // CSP strict uniquement sur l'API (JSON) — pas sur le frontend HTML.
        // La Swagger UI est exclue car elle charge des scripts et styles externes.
        if (str_starts_with($path, '/api/') && !str_starts_with($path, '/api/docs')) {
            $headers->set('Content-Security-Policy', "default-src 'none'");
        }
    }
}
