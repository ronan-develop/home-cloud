<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Interface\FileRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<File>
 * @method File|null find(mixed $id, LockMode|int|null $lockMode = null, int|null $lockVersion = null)
 * @method File[] findAll()
 * @method File|null findOneBy(array $criteria, array $orderBy = null)
 * @method File[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FileRepository extends ServiceEntityRepository implements FileRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, File::class);
    }

    /**
     * Retourne les fichiers filtrés + triés + paginés.
     *
     * @param array<string, string> $orderBy  ex: ['originalName' => 'ASC']
     * @return File[]
     */
    public function findFiltered(?string $search, ?string $folderId, array $orderBy, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('f')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($folderId !== null) {
            $qb->andWhere('IDENTITY(f.folder) = :folderId')
               ->setParameter('folderId', \Symfony\Component\Uid\Uuid::fromString($folderId)->toBinary());
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('f.originalName LIKE :q')->setParameter('q', '%' . $search . '%');
        }

        $allowed = ['originalName' => 'f.originalName', 'size' => 'f.size', 'createdAt' => 'f.createdAt'];
        foreach ($orderBy as $field => $dir) {
            if (isset($allowed[$field])) {
                $qb->addOrderBy($allowed[$field], strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC');
            }
        }

        return $qb->getQuery()->getResult();
    }

    /** Compte les fichiers avec filtres optionnels. */
    public function countFiltered(?string $search, ?string $folderId): int
    {
        $qb = $this->createQueryBuilder('f')->select('COUNT(f.id)');

        if ($folderId !== null) {
            $qb->andWhere('IDENTITY(f.folder) = :folderId')
               ->setParameter('folderId', \Symfony\Component\Uid\Uuid::fromString($folderId)->toBinary());
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('f.originalName LIKE :q')->setParameter('q', '%' . $search . '%');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Recherche les fichiers dont le nom contient $query (case-insensitive) pour un owner donné.
     *
     * @return File[]
     */
    public function searchByName(string $query, User $owner, int $limit = 20): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.originalName LIKE :q')
            ->andWhere('IDENTITY(f.owner) = :ownerId')
            ->setParameter('q', '%' . $query . '%')
            ->setParameter('ownerId', $owner->getId()->toBinary())
            ->orderBy('f.originalName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findById(Uuid $id): ?File
    {
        return $this->find($id);
    }

    public function findOneByNameInFolder(string $name, Folder $folder): ?File
    {
        return $this->findOneBy(['originalName' => $name, 'folder' => $folder]);
    }
}
