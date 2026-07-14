<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Album;
use App\Entity\Share;
use App\Entity\User;
use App\Interface\ResourceAccessCheckerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter Symfony pour les droits sur les albums.
 *
 * ALBUM_VIEW : owner OU partage actif (read ou write) — délégué à
 * ResourceAccessChecker, seule source de vérité owner/share.
 * ALBUM_DELETE : owner uniquement — un guest write agit dans l'album
 * (ajoute/retire des médias), il ne le détruit pas.
 *
 * @extends Voter<'ALBUM_VIEW'|'ALBUM_DELETE', Album>
 */
final class AlbumVoter extends Voter
{
    public const VIEW   = 'ALBUM_VIEW';
    public const DELETE = 'ALBUM_DELETE';

    public function __construct(
        private readonly ResourceAccessCheckerInterface $resourceAccessChecker,
    ) {}

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
        return match ($attribute) {
            self::VIEW   => $this->resourceAccessChecker->canRead($user, Share::RESOURCE_ALBUM, $subject->getId(), $subject->getOwner()),
            self::DELETE => $subject->getOwner()->getId()->equals($user->getId()),
        };
    }
}
