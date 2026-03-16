<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;
use Doctrine\DBAL\LockMode;

/**
 * Contrat pour l'accès aux données User.
 * Respecte le Dependency Inversion Principle (SOLID D).
 */
interface UserRepositoryInterface
{
    public function find(mixed $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null): ?User;

    /** @param array<string, mixed> $criteria */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?User;
}
