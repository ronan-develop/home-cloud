<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Share;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Share>
 *
 * @method Share|null find($id, $lockMode = null, $lockVersion = null)
 * @method Share|null findOneBy(array $criteria, array $orderBy = null)
 * @method Share[]    findAll()
 * @method Share[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Share::class);
    }

    /** Partages où l'utilisateur est owner OU guest. */
    public function findByUser(User $user, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.owner = :user OR s.guest = :user')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.owner = :user OR s.guest = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Vérifie si un user a un accès actif (non expiré) à une ressource. */
    public function findActiveShare(User $guest, string $resourceType, Uuid $resourceId, string $permission): ?Share
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.guest = :guest')
            ->andWhere('s.resourceType = :type')
            ->andWhere('s.resourceId = :rid')
            ->andWhere('s.permission = :perm OR s.permission = :write')
            ->andWhere('s.expiresAt IS NULL OR s.expiresAt > :now')
            ->setParameter('guest', $guest)
            ->setParameter('type', $resourceType)
            ->setParameter('rid', $resourceId)
            ->setParameter('perm', $permission)
            ->setParameter('write', 'write')
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
