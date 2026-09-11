<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LoginAttempt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginAttempt>
 */
class LoginAttemptRepository extends ServiceEntityRepository
{
    /** Seuil au-delà duquel une répétition est considérée suspecte (#386). */
    public const SUSPICIOUS_THRESHOLD = 5;

    /** Fenêtre de temps sur laquelle est évaluée la répétition. */
    public const SUSPICIOUS_WINDOW = '-15 minutes';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginAttempt::class);
    }

    public function save(LoginAttempt $attempt): void
    {
        $em = $this->getEntityManager();
        $em->persist($attempt);
        $em->flush();
    }

    public function countRecentByEmailHash(string $emailHash, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->where('la.emailHash = :emailHash')
            ->andWhere('la.createdAt >= :since')
            ->setParameter('emailHash', $emailHash)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countRecentByIp(string $ip, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->where('la.ip = :ip')
            ->andWhere('la.createdAt >= :since')
            ->setParameter('ip', $ip)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return LoginAttempt[] */
    public function findRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('la')
            ->orderBy('la.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Regroupe les tentatives récentes par email_hash au-delà du seuil suspect.
     *
     * @return array<int, array{emailHash: string, count: int}>
     */
    public function findSuspiciousByEmailHash(
        \DateTimeImmutable $since,
        int $threshold = self::SUSPICIOUS_THRESHOLD,
    ): array {
        return $this->createQueryBuilder('la')
            ->select('la.emailHash AS emailHash, COUNT(la.id) AS count')
            ->where('la.createdAt >= :since')
            ->setParameter('since', $since)
            ->groupBy('la.emailHash')
            ->having('COUNT(la.id) >= :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Regroupe les tentatives récentes par IP au-delà du seuil suspect.
     *
     * @return array<int, array{ip: string, count: int}>
     */
    public function findSuspiciousByIp(
        \DateTimeImmutable $since,
        int $threshold = self::SUSPICIOUS_THRESHOLD,
    ): array {
        return $this->createQueryBuilder('la')
            ->select('la.ip AS ip, COUNT(la.id) AS count')
            ->where('la.createdAt >= :since')
            ->andWhere('la.ip IS NOT NULL')
            ->setParameter('since', $since)
            ->groupBy('la.ip')
            ->having('COUNT(la.id) >= :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
