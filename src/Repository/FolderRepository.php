<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Folder;
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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Folder::class);
    }
}
