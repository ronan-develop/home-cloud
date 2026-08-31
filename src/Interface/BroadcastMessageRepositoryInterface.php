<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\BroadcastMessage;

/**
 * Contrat pour l'accès aux données BroadcastMessage.
 * Respecte le Dependency Inversion Principle (SOLID D).
 */
interface BroadcastMessageRepositoryInterface
{
    /** Dernier message broadcast in-app actif (createdAt le plus récent), ou null. */
    public function findLatest(): ?BroadcastMessage;
}
