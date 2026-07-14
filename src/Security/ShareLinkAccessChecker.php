<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\ShareLink;
use App\Interface\ShareLinkAccessCheckerInterface;
use App\Interface\ShareLinkRepositoryInterface;

/**
 * Vérifie si un couple (selector, token) désigne un lien de partage public actif.
 *
 * Symétrique de ShareAccessChecker (partage entre comptes), mais l'identité
 * n'est plus un User authentifié : c'est un secret porté par l'URL.
 */
final readonly class ShareLinkAccessChecker implements ShareLinkAccessCheckerInterface
{
    public function __construct(
        private ShareLinkRepositoryInterface $shareLinkRepository,
    ) {}

    public function resolve(string $selector, string $token): ?ShareLink
    {
        $link = $this->shareLinkRepository->findBySelector($selector);

        if ($link === null || !$link->verifyToken($token) || !$link->isActive()) {
            return null;
        }

        return $link;
    }
}
