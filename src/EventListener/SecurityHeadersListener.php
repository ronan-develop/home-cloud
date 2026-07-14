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
 * - X-Content-Type-Options: nosniff          → empêche le MIME sniffing navigateur
 * - X-Frame-Options: DENY                    → empêche l'embedding dans des iframes (clickjacking)
 * - Referrer-Policy: no-referrer             → ne transmet pas l'URL de la page précédente
 * - Strict-Transport-Security (prod only)    → force HTTPS, bloque le SSL-stripping
 *
 * Content-Security-Policy :
 * - Sur /api/* (hors /api/docs) : "default-src 'none'" → l'API ne sert que du JSON
 * - Sur le front HTML : policy permissive (scripts/styles inline autorisés,
 *   faute de nonces sur chaque <script> existant) mais qui bloque les
 *   sources EXTERNES, l'embedding en iframe et les plugins (object-src)
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class SecurityHeadersListener
{
    public function __construct(private readonly string $env)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'no-referrer');

        // HSTS : uniquement en prod — HTTPS non disponible en dev/test.
        if ('prod' === $this->env) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $path = $event->getRequest()->getPathInfo();

        // CSP strict sur l'API (JSON) — pas de script, style, image, etc.
        // La Swagger UI est exclue car elle charge des scripts et styles externes.
        if (str_starts_with($path, '/api/docs')) {
            return;
        }

        if (str_starts_with($path, '/api/')) {
            $headers->set('Content-Security-Policy', "default-src 'none'");

            return;
        }

        // CSP permissive sur le front HTML : bloque l'injection de sources
        // EXTERNES (le vecteur XSS le plus dangereux), mais autorise encore
        // les scripts/styles inline existants faute de nonces sur chacun.
        $headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'",
        );
    }
}
