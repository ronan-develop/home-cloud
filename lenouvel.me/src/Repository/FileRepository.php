<?php

namespace App\Repository;

use App\Entity\File;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<File>
 *
 * @method File|null find($id, $lockMode = null, $lockVersion = null)
 * @method File|null findOneBy(array $criteria, array $orderBy = null)
 * @method File[]    findAll()
 * @method File[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, File::class);
    }

    // Ajoute ici des méthodes custom si besoin

    /**
     * Retourne les fichiers d'un utilisateur, triés par date de création (DESC par défaut).
     */
    public function findByOwnerOrderedByCreatedAt(User $owner, string $order = 'DESC'): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('f.createdAt', $order)
            ->getQuery()
            ->getResult();
    }
}
