<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;

/**
 * Pose les en-têtes de cache navigateur sur les réponses servant un média.
 *
 * Rôle : Symfony répond par défaut en `max-age=0, must-revalidate`. Appliqué à
 * une image, ce défaut fait retélécharger chaque vignette à chaque scroll —
 * une galerie de 200 photos consultée cinq fois émettait 1000 requêtes là où
 * 200 suffisent.
 *
 * Une image est pourtant immuable : une vignette, une fois générée, ne change
 * plus jamais. Seule la suppression du média la rend obsolète, et la route
 * répond alors 404 — le cache navigateur ne peut donc pas servir un contenu
 * périmé.
 *
 * Centralisé plutôt que répété : quatre routes servent des images (vignette
 * API, vignette galerie, plein écran, partage public), et un oubli sur l'une
 * d'elles passerait inaperçu.
 *
 * Ne concerne pas le téléchargement de fichier (`app_file_download`) : un
 * téléchargement n'a pas à rester en cache.
 */
final readonly class MediaCacheHeaders
{
    /**
     * Une heure : assez pour couvrir une session de navigation, assez court
     * pour qu'un média re-partagé ou déplacé ne traîne pas longtemps.
     */
    private const MAX_AGE = 3600;

    /**
     * @param bool $shared Autorise les caches partagés (proxies, CDN). Réservé
     *                     aux partages par lien, accessibles sans compte : le
     *                     secret est dans l'URL, pas dans la session. Laisser à
     *                     false pour tout média authentifié, qu'un cache partagé
     *                     pourrait sinon servir à un autre utilisateur.
     */
    public function applyTo(Response $response, bool $shared = false): void
    {
        if ($shared) {
            $response->setPublic();
        } else {
            $response->setPrivate();
        }

        $response->setMaxAge(self::MAX_AGE);
    }
}
