<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class PasswordResetService implements PasswordResetServiceInterface
{
    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Valide le token et met à jour le mot de passe de l'utilisateur.
     * @throws ResetPasswordExceptionInterface
     */
    public function resetPassword(string $token, string $newPassword): void
    {
        $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        $user->setPassword(password_hash($newPassword, PASSWORD_BCRYPT));
        $this->entityManager->flush();
        $this->resetPasswordHelper->removeResetRequest($token);
    }
}
