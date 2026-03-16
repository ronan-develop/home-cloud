<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Contrat de résolution de l'utilisateur authentifié depuis le contexte de sécurité.
 * Respecte le Dependency Inversion Principle (SOLID D).
 */
interface AuthenticationResolverInterface
{
    public function getAuthenticatedUser(): ?User;

    /**
     * @throws UnauthorizedHttpException si aucun utilisateur authentifié
     */
    public function requireUser(): User;
}
