<?php

namespace App\Repository;

use App\Entity\File;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Query;

class FileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, File::class);
    }

    /**
     * Retourne une requête DQL pour les fichiers d’un utilisateur (pour Pagerfanta)
     */
    public function getFilesForUserQuery(User $user): Query
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('f.uploadedAt', 'DESC')
            ->getQuery();
    }
}
