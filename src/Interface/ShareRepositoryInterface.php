<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Share;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Symfony\Component\Uid\Uuid;

/**
 * Contrat pour l'accès aux données Share.
 * Respecte le Dependency Inversion Principle (SOLID D).
 */
interface ShareRepositoryInterface
{
    public function find(mixed $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null): ?Share;

    /** @return Share[] */
    public function findByUser(User $user, int $limit = 20, int $offset = 0): array;

    public function countByUser(User $user): int;

    public function findActiveShare(User $guest, string $resourceType, Uuid $resourceId, string $permission): ?Share;

    /**
     * Partages actifs (non révoqués, non expirés) reçus par ce guest.
     *
     * @return Share[]
     */
    public function findActiveByGuest(User $guest): array;

    /** Supprime tous les shares pointant vers cette ressource (nettoyage à la suppression). */
    public function deleteByResource(string $resourceType, Uuid $resourceId): void;
}
