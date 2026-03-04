<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Folder;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Folder>
 * @method Folder|null find(mixed $id, LockMode|int|null $lockMode = null, int|null $lockVersion = null)
 * @method Folder[] findAll()
 * @method Folder|null findOneBy(array $criteria, array $orderBy = null)
 * @method Folder[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FolderRepository extends ServiceEntityRepository
{
    private readonly ManagerRegistry $registry;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Folder::class);
        $this->registry = $registry;
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
