<?php

namespace App\Security\Voter;

use App\Entity\Photo;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class PhotoVoter extends Voter
{
    public const VIEW = 'view';

    protected function supports(string $attribute, $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof Photo;
    }

    /**
     * Autorise l'accès si l'utilisateur est propriétaire de la photo ou admin.
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }
        // Propriétaire de la photo
        if ($subject->getUser() instanceof User && $user instanceof User && $subject->getUser()->getId() === $user->getId()) {
            return true;
        }
        // Admin
        if (method_exists($user, 'getRoles') && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }
        return false;
    }
}
