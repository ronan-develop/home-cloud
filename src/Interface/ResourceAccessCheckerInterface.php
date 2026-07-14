<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;
use Symfony\Component\Uid\Uuid;

/**
 * Contrat unifiant ownership et partage pour l'accès à une ressource.
 * Respecte le Dependency Inversion Principle (SOLID D).
 */
interface ResourceAccessCheckerInterface
{
    public function canRead(User $user, string $resourceType, Uuid $resourceId, User $owner): bool;

    public function canWrite(User $user, string $resourceType, Uuid $resourceId, User $owner): bool;
}
