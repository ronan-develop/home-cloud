<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Interface\AuthorizationCheckerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Centralized authorization logic.
 * Validates ownership, cycles, and permissions.
 *
 * Responsibility: Access control only.
 */
final class AuthorizationChecker implements AuthorizationCheckerInterface
{
    /**
     * Check if user owns the entity.
     *
     * @throws AccessDeniedHttpException
     */
    public function assertOwns(File|Folder $entity, User $requester): void
    {
        if ((string) $entity->getOwner()->getId() !== (string) $requester->getId()) {
            $className = (new \ReflectionClass($entity))->getShortName();
            throw new AccessDeniedHttpException(
                sprintf('You do not own this %s', $className)
            );
        }
    }

    /**
     * Check if moving targetFolder under sourceFolder would create a cycle.
     * Prevents: A > B > C, then move B's parent to C (creates A > B > C > B).
     *
     * @return bool true if cycle would be created
     */
    public function wouldCreateCycle(Folder $sourceFolder, Folder $targetFolder): bool
    {
        $current = $targetFolder;
        while ($current !== null) {
            if ($current->getId()->equals($sourceFolder->getId())) {
                return true;
            }
            $current = $current->getParent();
        }
        return false;
    }
}
