<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AlbumMedia;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlbumMedia>
 */
class AlbumMediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlbumMedia::class);
    }
}
