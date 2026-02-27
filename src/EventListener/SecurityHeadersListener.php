<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute les headers de sécurité HTTP sur toutes les réponses de l'API.
 *
 * Headers ajoutés :
 * - X-Content-Type-Options: nosniff  → empêche le MIME sniffing navigateur
 * - X-Frame-Options: DENY            → empêche l'embedding dans des iframes (clickjacking)
 * - Referrer-Policy: no-referrer     → ne transmet pas l'URL de la page précédente
 *
 * Note : X-Content-Type-Options est également ajouté individuellement sur les
 * réponses de téléchargement (FileDownloadController, MediaThumbnailController)
 * pour être explicite. Ce listener le garantit globalement sur toutes les routes.
 *
 * Pourquoi un EventListener et non public/index.php ?
 * L'EventListener est testé par le kernel Symfony, n'est pas dépendant de la
 * configuration du serveur web, et est portable entre PHP-FPM et CLI.
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
    }
}
