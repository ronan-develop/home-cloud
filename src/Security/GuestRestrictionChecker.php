<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Exception\GuestNotAllowedException;

/**
 * Un compte invité (accountType=guest) est exclusivement lecture/téléchargement :
 * pas d'upload, pas de création de dossier/album, pas d'espace personnel.
 * Vaut indépendamment de la permission (read|write) d'un Share reçu — un
 * invité reste lecture seule même via un partage en write.
 */
final readonly class GuestRestrictionChecker
{
    public function denyUnlessFullAccount(User $user): void
    {
        if ($user->isGuest()) {
            throw new GuestNotAllowedException();
        }
    }
}
