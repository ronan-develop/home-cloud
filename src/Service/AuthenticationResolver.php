<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Centralized user extraction from security context.
 * Consolidates auth logic scattered in FileProcessor + FolderProcessor.
 *
 * Responsibility: Token → User mapping only.
 */
final class AuthenticationResolver
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UserRepository $userRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Extract authenticated user from current security context.
     * Returns null if unauthenticated.
     */
    public function getAuthenticatedUser(): ?User
    {
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            $this->logger->debug('No security token found');
            return null;
        }

        $user = $token->getUser();

        // Case 1: Already a User instance (JWT, Session)
        if ($user instanceof User) {
            return $user;
        }

        // Case 2: User is a string (email)
        if (is_string($user) && filter_var($user, FILTER_VALIDATE_EMAIL)) {
            return $this->userRepository->findOneBy(['email' => $user]);
        }

        $this->logger->warning('Unexpected user type in token', [
            'type' => get_class($user),
        ]);
        return null;
    }

    /**
     * Assert user is authenticated, throw if not.
     *
     * @throws UnauthorizedHttpException
     */
    public function requireUser(): User
    {
        $user = $this->getAuthenticatedUser();
        if (!$user) {
            throw new UnauthorizedHttpException('Bearer realm="api"', 'Authentication required');
        }
        return $user;
    }
}
