<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TakeoutImport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TakeoutImport>
 */
class TakeoutImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TakeoutImport::class);
    }

    public function save(TakeoutImport $import): void
    {
        $em = $this->getEntityManager();
        $em->persist($import);
        $em->flush();
    }
}
