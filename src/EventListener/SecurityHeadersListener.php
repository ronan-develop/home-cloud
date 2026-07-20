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
 * - script-src autorise `data:` : AssetMapper mappe les imports CSS des
 *   modules JS (`import './styles/app.css'`) vers un faux module
 *   `data:application/javascript,` — sans ce `data:`, la CSP bloque ce
 *   faux module et interrompt toute la chaîne d'imports du module JS
 *   principal (app.js et tout ce qu'il importe). Risque limité : `data:`
 *   n'autorise que du contenu inline déjà rendu par le serveur, comme
 *   `'unsafe-inline'` déjà présent.
 *
 * Dérogation /files/{id}/view (#280) : le viewer PDF (#241) embarque cette
 * route dans une <iframe> same-origin — DENY/'none' bloquerait cet embedding
 * même en same-origin. Remplacé par SAMEORIGIN/'self' UNIQUEMENT sur cette
 * route, pour ne pas affaiblir la protection anti-clickjacking ailleurs.
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

        $path = $event->getRequest()->getPathInfo();
        $isFileViewRoute = (bool) preg_match('#^/files/[^/]+/view$#', $path);

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', $isFileViewRoute ? 'SAMEORIGIN' : 'DENY');
        $headers->set('Referrer-Policy', 'no-referrer');

        // HSTS : uniquement en prod — HTTPS non disponible en dev/test.
        if ('prod' === $this->env) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

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
        $frameAncestors = $isFileViewRoute ? "frame-ancestors 'self'" : "frame-ancestors 'none'";
        $headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline' data:; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; object-src 'none'; base-uri 'self'; {$frameAncestors}",
        );
    }
}
