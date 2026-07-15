<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\ShareLink;

/**
 * Contrat de résolution d'un lien de partage public à partir de son secret.
 * Respecte le Dependency Inversion Principle (SOLID D).
 */
interface ShareLinkAccessCheckerInterface
{
    /**
     * Retourne le ShareLink si (selector, token) désigne un lien actif, sinon null.
     * Ne distingue jamais "inconnu" de "invalide/expiré/révoqué" dans le retour :
     * à l'appelant de répondre 404 dans tous les cas, pour ne pas confirmer
     * l'existence d'un selector à un attaquant.
     */
    public function resolve(string $selector, string $token): ?ShareLink;
}
