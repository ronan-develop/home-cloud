<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Interface\BroadcastMessageRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Injecte `unreadBroadcastMessage` dans tous les templates Twig pour la
 * bannière in-app du broadcast admin (#361, suite de #283).
 *
 * Contrairement à ChangelogGlobalsExtension (compteur), on expose l'objet
 * BroadcastMessage complet : la bannière doit afficher le contenu réel du
 * message, pas juste signaler une nouveauté.
 */
final class BroadcastGlobalsExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly BroadcastMessageRepositoryInterface $broadcastMessageRepository,
        private readonly Security $security,
    ) {}

    public function getGlobals(): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return ['unreadBroadcastMessage' => null];
        }

        $latest = $this->broadcastMessageRepository->findLatest();

        if ($latest === null) {
            return ['unreadBroadcastMessage' => null];
        }

        $seenAt = $user->getLastBroadcastSeenAt();

        if ($seenAt !== null && $seenAt >= $latest->getCreatedAt()) {
            return ['unreadBroadcastMessage' => null];
        }

        return ['unreadBroadcastMessage' => $latest];
    }
}
