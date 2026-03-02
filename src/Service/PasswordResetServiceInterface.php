<?php

namespace App\Service;

interface PasswordResetServiceInterface
{
    /**
     * @throws \SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface
     */
    public function resetPassword(string $token, string $newPassword): void;
}
