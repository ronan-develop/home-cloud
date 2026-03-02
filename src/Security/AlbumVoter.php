<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Album;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter Symfony pour les droits sur les albums (SRP — ownership centralisé).
 *
 * Responsabilité unique : vérifier si l'utilisateur connecté est propriétaire
 * de l'album pour les attributs ALBUM_VIEW et ALBUM_DELETE.
 *
 * Remplace les doubles ownership checks inline dans AlbumWebController.
 *
 * @extends Voter<'ALBUM_VIEW'|'ALBUM_DELETE', Album>
 */
final class AlbumVoter extends Voter
{
    public const VIEW   = 'ALBUM_VIEW';
    public const DELETE = 'ALBUM_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::DELETE], true)
            && $subject instanceof Album;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?\Symfony\Component\Security\Core\Authorization\Voter\Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Album $subject */
        return $subject->getOwner()->getId()->equals($user->getId());
    }
}
