<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Share;
use App\Entity\User;
use App\Interface\ResourceAccessCheckerInterface;
use App\Interface\ShareAccessCheckerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Source de vérité unique pour l'accès à une ressource : owner OU partage actif.
 *
 * Centralise la logique auparavant dupliquée dans FileProvider, AlbumProvider,
 * MediaProvider, FileDownloadController et MediaThumbnailController.
 *
 * Pas de cache : ShareAccessChecker::canAccess() rejoue la requête à chaque
 * appel, donc révocation et expiration d'un Share sont effectives immédiatement.
 */
final readonly class ResourceAccessChecker implements ResourceAccessCheckerInterface
{
    public function __construct(
        private ShareAccessCheckerInterface $shareAccessChecker,
    ) {}

    public function canRead(User $user, string $resourceType, Uuid $resourceId, User $owner): bool
    {
        return $owner->getId()->equals($user->getId())
            || $this->shareAccessChecker->canAccess($user, $resourceType, $resourceId, Share::PERMISSION_READ);
    }

    public function canWrite(User $user, string $resourceType, Uuid $resourceId, User $owner): bool
    {
        return $owner->getId()->equals($user->getId())
            || $this->shareAccessChecker->canAccess($user, $resourceType, $resourceId, Share::PERMISSION_WRITE);
    }
}
