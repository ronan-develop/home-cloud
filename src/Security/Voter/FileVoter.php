<?php

namespace App\Security\Voter;

use App\Entity\File;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class FileVoter extends Voter
{
    public const DOWNLOAD = 'FILE_DOWNLOAD';
    public const DELETE = 'FILE_DELETE';

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::DOWNLOAD, self::DELETE], true)
            && $subject instanceof File;
    }

    /**
     * @param string $attribute
     * @param mixed $subject
     * @param TokenInterface $token
     * TODO Symfony 7.4+ : ajouter l'argument Vote|null $vote à la signature
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return false;
        }
        // Seul l'uploader ou un admin peut agir
        if ($subject->getOwner() instanceof User && $user instanceof User && $subject->getOwner()->getId() === $user->getId()) {
            return true;
        }
        // Autoriser les admins (à adapter selon votre logique de rôles)
        if (method_exists($user, 'getRoles') && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }
        return false;
    }
}
