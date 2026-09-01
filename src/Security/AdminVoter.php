<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter Symfony pour l'accès aux écrans admin (#373). Généralise
 * BroadcastAdminChecker (whitelist par email, pas de ROLE_ADMIN Symfony)
 * derrière un attribut IsGranted réutilisable, sur le modèle d'AlbumVoter.
 *
 * @extends Voter<'ADMIN', null>
 */
final class AdminVoter extends Voter
{
    public const ADMIN = 'ADMIN';

    public function __construct(
        private readonly BroadcastAdminChecker $adminChecker,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ADMIN;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->adminChecker->isAdmin($user);
    }
}
