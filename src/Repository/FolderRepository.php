<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Folder;
use App\Entity\User;
use App\Interface\FolderRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Folder>
 * @method Folder|null find(mixed $id, LockMode|int|null $lockMode = null, int|null $lockVersion = null)
 * @method Folder[] findAll()
 * @method Folder|null findOneBy(array $criteria, array $orderBy = null)
 * @method Folder[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FolderRepository extends ServiceEntityRepository implements FolderRepositoryInterface
{
    private readonly ManagerRegistry $registry;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Folder::class);
        $this->registry = $registry;
    }

    public function find(mixed $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null): ?Folder
    {
        return parent::find($id, $lockMode, $lockVersion);
    }

    /** @param array<string, mixed> $criteria */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?Folder
    {
        return parent::findOneBy($criteria, $orderBy);
    }

    /** @param array<string, mixed> $criteria */
    public function count(array $criteria = []): int
    {
        return parent::count($criteria);
    }

    /**
     * @param array<string, mixed> $criteria
     * @return Folder[]
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return parent::findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Charge tous les dossiers de l'owner et les organise en arbre imbriqué.
     * Chaque nœud : ['id', 'name', 'url', 'isOpen' (ancêtre du dossier courant), 'isActive' (dossier courant), 'children'].
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAllAsTree(User $owner, ?Folder $currentFolder): array
    {
        $all = $this->createQueryBuilder('f')
            ->where('IDENTITY(f.owner) = :ownerId')
            ->setParameter('ownerId', $owner->getId()->toBinary())
            ->orderBy('f.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Collecte les IDs des ancêtres du dossier courant (pour ouvrir le bon chemin)
        $openIds = [];
        if ($currentFolder !== null) {
            $ancestor = $currentFolder;
            while ($ancestor !== null) {
                $openIds[$ancestor->getId()->toRfc4122()] = true;
                $ancestor = $ancestor->getParent();
            }
        }

        return $this->buildTree($all, null, $openIds, $currentFolder);
    }

    /**
     * @param Folder[] $all
     * @param array<string, bool> $openIds
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(array $all, ?Folder $parent, array $openIds, ?Folder $currentFolder): array
    {
        $nodes = [];
        foreach ($all as $folder) {
            $sameParent = $parent === null
                ? $folder->getParent() === null
                : $folder->getParent() !== null && $folder->getParent()->getId()->equals($parent->getId());

            if (!$sameParent) {
                continue;
            }

            $id = $folder->getId()->toRfc4122();
            $nodes[] = [
                'id'       => $id,
                'name'     => $folder->getName(),
                'url'      => '/?folder=' . $id,
                'isActive' => $currentFolder !== null && $folder->getId()->equals($currentFolder->getId()),
                'isOpen'   => isset($openIds[$id]),
                'children' => $this->buildTree($all, $folder, $openIds, $currentFolder),
            ];
        }

        return $nodes;
    }

    /**
     * Recherche les dossiers dont le nom contient $query (case-insensitive) pour un owner donné.
     *
     * @return Folder[]
     */
    public function searchByName(string $query, User $owner, int $limit = 20): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.name LIKE :q')
            ->andWhere('IDENTITY(f.owner) = :ownerId')
            ->setParameter('q', '%' . $query . '%')
            ->setParameter('ownerId', $owner->getId()->toBinary())
            ->orderBy('f.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les UUIDs de tous les descendants d'un dossier (CTE récursive MySQL 10.3+).
     * Descend enfant → enfant → … jusqu'aux feuilles.
     *
     * @return string[] Liste d'UUIDs au format RFC4122
     */
    public function findDescendantIds(Folder $folder): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = <<<SQL
            WITH RECURSIVE descendants AS (
                SELECT f.id, f.parent_id
                FROM folders f
                WHERE f.parent_id = UNHEX(:folderId)

                UNION ALL

                SELECT c.id, c.parent_id
                FROM folders c
                INNER JOIN descendants d ON c.parent_id = d.id
            )
            SELECT LOWER(HEX(id)) AS id
            FROM descendants
        SQL;

        $hexId = str_replace('-', '', (string) $folder->getId());
        $rows = $conn->executeQuery($sql, ['folderId' => $hexId])->fetchAllAssociative();

        return array_map(static function (array $row): string {
            $hex = $row['id'];
            return sprintf(
                '%s-%s-%s-%s-%s',
                substr($hex, 0, 8),
                substr($hex, 8, 4),
                substr($hex, 12, 4),
                substr($hex, 16, 4),
                substr($hex, 20)
            );
        }, $rows);
    }

    /**
     * Récupère les UUIDs de tous les ancêtres d'un dossier en une seule requête SQL.
     * Utilise une CTE récursive (WITH RECURSIVE) supportée par MariaDB 10.3+.
     * Remonte la chaîne parent → parent → … jusqu'à la racine.
     * Retourne un tableau d'UUID (strings) sans charger les entités Doctrine.
     * Suffisant pour la détection de cycle (comparaison d'IDs uniquement).
     * @return string[] Liste d'UUIDs (BIN(16) converti en hex lisible)
     */
    public function findAncestorIds(Folder $folder): array
    {
        $conn = $this->registry->getConnection();

        $sql = <<<SQL
                WITH RECURSIVE ancestors AS (
                    SELECT f.id, f.parent_id
                    FROM folders f
                    WHERE f.id = UNHEX(:folderId)

                    UNION ALL

                    SELECT p.id, p.parent_id
                    FROM folders p
                    INNER JOIN ancestors a ON p.id = a.parent_id
                )
                SELECT LOWER(HEX(id)) AS id
                FROM ancestors
                WHERE id != UNHEX(:folderId)
            SQL;

        $hexId = str_replace('-', '', (string) $folder->getId());
        $rows = $conn->executeQuery($sql, ['folderId' => $hexId])->fetchAllAssociative();

        return array_map(static function (array $row): string {
            $hex = $row['id'];
            return sprintf(
                '%s-%s-%s-%s-%s',
                substr($hex, 0, 8),
                substr($hex, 8, 4),
                substr($hex, 12, 4),
                substr($hex, 16, 4),
                substr($hex, 20)
            );
        }, $rows);
    }
}
