<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BroadcastMessage;
use App\Interface\BroadcastMessageRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BroadcastMessage>
 *
 * @method BroadcastMessage|null find($id, $lockMode = null, $lockVersion = null)
 * @method BroadcastMessage|null findOneBy(array $criteria, array $orderBy = null)
 * @method BroadcastMessage[]    findAll()
 * @method BroadcastMessage[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BroadcastMessageRepository extends ServiceEntityRepository implements BroadcastMessageRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BroadcastMessage::class);
    }

    public function findLatest(): ?BroadcastMessage
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
