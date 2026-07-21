<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\ShareLink;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Symfony\Component\Uid\Uuid;

/**
 * Contrat pour l'accès aux données ShareLink.
 * Respecte le Dependency Inversion Principle (SOLID D).
 */
interface ShareLinkRepositoryInterface
{
    public function find(mixed $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null): ?ShareLink;

    public function findBySelector(string $selector): ?ShareLink;

    /** @return ShareLink[] */
    public function findByOwner(User $owner, int $limit = 100): array;

    /** @return ShareLink[] */
    public function findActiveByResource(string $resourceType, Uuid $resourceId): array;

    /** Supprime tous les liens pointant vers cette ressource (nettoyage à la suppression). */
    public function deleteByResource(string $resourceType, Uuid $resourceId): void;

    /** Purge les liens révoqués depuis avant $threshold. Retourne le nombre de liens supprimés. */
    public function deleteRevokedOlderThan(\DateTimeImmutable $threshold): int;
}
