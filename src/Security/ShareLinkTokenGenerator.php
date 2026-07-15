<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Génère le triplet (selector, token en clair, hash du token) d'un lien de
 * partage public. Ne persiste rien — SRP : génération seule.
 *
 * Le token en clair n'existe qu'une fois, ici, pour être placé dans l'URL
 * envoyée à l'utilisateur ; seul son hash est destiné à être stocké en base.
 * `random_bytes` (CSPRNG) exclut tout token devinable — jamais `uniqid()`,
 * `rand()`, ni un UUID (qui encode un timestamp).
 */
final readonly class ShareLinkTokenGenerator
{
    public function generate(): GeneratedShareLinkToken
    {
        $selector = bin2hex(random_bytes(16));
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        return new GeneratedShareLinkToken($selector, $token, $hashedToken);
    }
}
