<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Album;
use App\Entity\Folder;
use App\Entity\Share;
use App\Interface\AuthenticationResolverInterface;
use App\Interface\OwnershipCheckerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Centralise les vérifications d'ownership sur les ressources.
 * Élimine les duplications de `getId() === getId()` dans les processors.
 *
 * Responsabilité : access control ownership uniquement.
 * Utilise AuthenticationResolver pour résoudre l'utilisateur courant.
 */
final readonly class OwnershipChecker implements OwnershipCheckerInterface
{
    public function __construct(
        private AuthenticationResolverInterface $authResolver,
        private LoggerInterface $logger,
    ) {}

    public function isOwner(Folder|Album|Share $resource): bool
    {
        $user = $this->authResolver->getAuthenticatedUser();

        if ($user === null) {
            return false;
        }

        $isOwner = $resource->getOwner()->getId()->equals($user->getId());

        if (!$isOwner) {
            $this->logger->warning('Ownership check failed', [
                'user_id'       => (string) $user->getId(),
                'resource_type' => (new \ReflectionClass($resource))->getShortName(),
                'resource_id'   => (string) $resource->getId(),
                'owner_id'      => (string) $resource->getOwner()->getId(),
            ]);
        }

        return $isOwner;
    }

    public function denyUnlessOwner(Folder|Album|Share $resource): void
    {
        $user = $this->authResolver->getAuthenticatedUser();

        if ($user === null) {
            throw new AccessDeniedHttpException('You must be authenticated');
        }

        if (!$this->isOwner($resource)) {
            throw new AccessDeniedHttpException(
                sprintf('You are not the owner of this %s', (new \ReflectionClass($resource))->getShortName())
            );
        }
    }
}
