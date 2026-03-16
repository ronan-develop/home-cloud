<?php

declare(strict_types=1);

namespace App\Interface;

interface PasswordResetServiceInterface
{
    /**
     * @throws \SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface
     */
    public function resetPassword(string $token, string $newPassword): void;
}
