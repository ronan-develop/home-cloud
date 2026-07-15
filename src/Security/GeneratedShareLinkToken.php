<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Résultat de ShareLinkTokenGenerator::generate().
 * `token` est en clair : à mettre dans l'URL, jamais en base.
 * `hashedToken` est ce qui doit être persisté sur ShareLink.
 */
final readonly class GeneratedShareLinkToken
{
    public function __construct(
        public string $selector,
        public string $token,
        public string $hashedToken,
    ) {}
}
