<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;

interface PasswordResetInitiatorInterface
{
    /**
     * Génère un token de reset password et envoie l'email correspondant.
     * Ne fait aucune vérification de rate limiting (responsabilité de
     * l'appelant — cf. limiters dédiés du flux self-service).
     */
    public function sendResetLink(User $user, Request $request): void;
}
