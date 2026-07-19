<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Media;
use App\Entity\UploadBatch;
use App\Interface\UploadBatchRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<UploadBatch>
 */
class UploadBatchRepository extends ServiceEntityRepository implements UploadBatchRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UploadBatch::class);
    }

    public function findById(Uuid $id): ?UploadBatch
    {
        return $this->find($id);
    }

    public function countProcessed(UploadBatch $batch): int
    {
        // Compte les File du lot pour lesquels un Media existe. On joint Media
        // sur f.batch = :batch (index sur batch_id) : l'état réel en base fait
        // foi, ce qui reste correct même si le worker rejoue un message.
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from(\App\Entity\File::class, 'f')
            ->innerJoin(Media::class, 'm', 'WITH', 'm.file = f')
            ->andWhere('IDENTITY(f.batch) = :batchId')
            ->setParameter('batchId', $batch->getId()->toBinary())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
