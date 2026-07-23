<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;

/**
 * Garde applicative de l'écran /admin/broadcast (#283). Pas de ROLE_ADMIN
 * Symfony (aucun rôle différencié n'existe dans le projet) — whitelist
 * explicite par email, pour rester correct même si un guest est un jour
 * ajouté sur l'instance ronan.lenouvel.me elle-même.
 */
final readonly class BroadcastAdminChecker
{
    public function __construct(
        private string $adminEmail,
    ) {}

    public function isAdmin(User $user): bool
    {
        return $this->adminEmail !== '' && $user->getEmail() === $this->adminEmail;
    }
}
