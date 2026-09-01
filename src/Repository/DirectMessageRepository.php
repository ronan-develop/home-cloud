<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DirectMessage;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DirectMessage>
 */
class DirectMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DirectMessage::class);
    }

    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('dm')
            ->select('COUNT(dm.id)')
            ->andWhere('dm.recipient = :user')
            ->andWhere('dm.readAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return DirectMessage[] */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('dm')
            ->andWhere('dm.recipient = :user')
            ->setParameter('user', $user)
            ->orderBy('dm.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
