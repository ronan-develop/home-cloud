<?php

namespace App\Repository;

use App\Entity\UploadableFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UploadableFile>
 *
 * @method UploadableFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method UploadableFile|null findOneBy(array $criteria, array $orderBy = null)
 * @method UploadableFile[]    findAll()
 * @method UploadableFile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UploadableFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UploadableFile::class);
    }

    //    /**
    //     * @return UploadableFile[] Returns an array of UploadableFile objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?UploadableFile
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
