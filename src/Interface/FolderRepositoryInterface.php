<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Folder;
use App\Entity\User;
use Doctrine\DBAL\LockMode;

/**
 * Contrat pour l'accès aux données Folder.
 * Respecte le Dependency Inversion Principle (SOLID D).
 */
interface FolderRepositoryInterface
{
    public function find(mixed $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null): ?Folder;

    /** @param array<string, mixed> $criteria */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?Folder;

    public function count(array $criteria = []): int;

    /**
     * @param array<string, mixed> $criteria
     * @return Folder[]
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array;

    /**
     * Retourne les UUIDs de tous les descendants (CTE récursive).
     *
     * @return string[]
     */
    public function findDescendantIds(Folder $folder): array;

    /**
     * Retourne les UUIDs de tous les ancêtres (CTE récursive).
     *
     * @return string[]
     */
    public function findAncestorIds(Folder $folder): array;

    /**
     * Retourne l'arborescence complète d'un owner pour la navigation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllAsTree(User $owner, ?Folder $currentFolder): array;

    /**
     * Recherche par nom (case-insensitive) pour un owner.
     *
     * @return Folder[]
     */
    public function searchByName(string $query, User $owner, int $limit = 20): array;
}
