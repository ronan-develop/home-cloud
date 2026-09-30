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
            ->andWhere('dm.recipient = :userId')
            ->andWhere('dm.readAt IS NULL')
            ->setParameter('userId', $user->getId(), 'uuid')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return DirectMessage[] */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('dm')
            ->andWhere('dm.recipient = :userId')
            ->setParameter('userId', $user->getId(), 'uuid')
            ->orderBy('dm.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // #533 : "Tout marquer comme lu" — UPDATE bulk plutôt qu'un foreach +
    // markAsRead() + flush par message (potentiellement des dizaines de
    // messages non lus d'un coup).
    public function markAllAsReadForRecipient(User $recipient): void
    {
        $this->createQueryBuilder('dm')
            ->update()
            ->set('dm.readAt', ':now')
            ->andWhere('dm.recipient = :userId')
            ->andWhere('dm.readAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable(), \Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE)
            ->setParameter('userId', $recipient->getId(), 'uuid')
            ->getQuery()
            ->execute();
    }
}
