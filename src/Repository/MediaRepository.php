<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Media>
 * @method Media|null find(mixed $id, LockMode|int|null $lockMode = null, int|null $lockVersion = null)
 * @method Media[] findAll()
 * @method Media|null findOneBy(array $criteria, array $orderBy = null)
 * @method Media[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }
}
