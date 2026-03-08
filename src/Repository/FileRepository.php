<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\File;
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

    /**
     * Find a file by ID (implements FileRepositoryInterface).
     *
     * @return File|null
     */
    public function findById(Uuid $id): ?File
    {
        return $this->find($id);
    }
}
