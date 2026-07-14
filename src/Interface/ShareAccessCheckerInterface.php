<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;
use Symfony\Component\Uid\Uuid;

/**
 * Contrat de vérification d'accès via partage (Share) actif.
 * Respecte le Dependency Inversion Principle (SOLID D).
 */
interface ShareAccessCheckerInterface
{
    public function canAccess(User $user, string $resourceType, Uuid $resourceId, string $permission = 'read'): bool;
}
