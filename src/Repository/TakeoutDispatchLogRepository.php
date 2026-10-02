<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TakeoutDispatchLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TakeoutDispatchLog>
 */
class TakeoutDispatchLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TakeoutDispatchLog::class);
    }

    public function save(TakeoutDispatchLog $log): void
    {
        $em = $this->getEntityManager();
        $em->persist($log);
        $em->flush();
    }

    /**
     * @return list<TakeoutDispatchLog>
     */
    public function findRecent(int $limit = 50): array
    {
        return $this->findBy([], ['createdAt' => 'DESC'], $limit);
    }

    /**
     * Un seul DELETE borné par date (index created_at) : pas de chargement
     * d'entités, adapté au LVE.
     */
    public function purgeOlderThan(\DateTimeImmutable $threshold): int
    {
        return (int) $this->createQueryBuilder('l')
            ->delete()
            ->where('l.createdAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }

    /**
     * @return array<string, int> nombre de cycles par issue depuis $since (0 si absente)
     */
    public function countByOutcomeSince(\DateTimeImmutable $since): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.outcome AS outcome, COUNT(l.id) AS total')
            ->where('l.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('l.outcome')
            ->getQuery()
            ->getArrayResult();

        $counts = array_fill_keys(TakeoutDispatchLog::OUTCOMES, 0);
        foreach ($rows as $row) {
            $counts[$row['outcome']] = (int) $row['total'];
        }

        return $counts;
    }
}
