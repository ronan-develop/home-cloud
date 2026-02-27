<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\ShareRepository;
use Symfony\Component\Uid\Uuid;

/**
 * Vérifie si un utilisateur a accès à une ressource via un partage actif.
 */
final class ShareAccessChecker
{
    public function __construct(
        private readonly ShareRepository $shareRepository,
    ) {}

    public function canAccess(User $user, string $resourceType, Uuid $resourceId, string $permission = 'read'): bool
    {
        return $this->shareRepository->findActiveShare($user, $resourceType, $resourceId, $permission) !== null;
    }
}
