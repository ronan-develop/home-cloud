<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShareLink;
use App\Entity\User;
use App\Interface\ShareLinkRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ShareLink>
 *
 * @method ShareLink|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShareLink|null findOneBy(array $criteria, array $orderBy = null)
 * @method ShareLink[]    findAll()
 * @method ShareLink[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ShareLinkRepository extends ServiceEntityRepository implements ShareLinkRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShareLink::class);
    }

    public function find(mixed $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null): ?ShareLink
    {
        return parent::find($id, $lockMode, $lockVersion);
    }

    public function findBySelector(string $selector): ?ShareLink
    {
        return $this->findOneBy(['selector' => $selector]);
    }

    public function findByOwner(User $owner, int $limit = 100): array
    {
        return $this->createQueryBuilder('sl')
            ->where('sl.owner = :owner')
            ->setParameter('owner', $owner->getId(), 'uuid')
            ->orderBy('sl.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return ShareLink[] */
    public function findActiveByResource(string $resourceType, Uuid $resourceId): array
    {
        return $this->createQueryBuilder('sl')
            ->where('sl.resourceType = :type')
            ->andWhere('sl.resourceId = :rid')
            ->andWhere('sl.revokedAt IS NULL')
            ->andWhere('sl.expiresAt > :now')
            ->setParameter('type', $resourceType)
            ->setParameter('rid', $resourceId, 'uuid')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /** Supprime tous les liens pointant vers cette ressource (nettoyage à la suppression). */
    public function deleteByResource(string $resourceType, Uuid $resourceId): void
    {
        $this->createQueryBuilder('sl')
            ->delete()
            ->where('sl.resourceType = :type')
            ->andWhere('sl.resourceId = :rid')
            ->setParameter('type', $resourceType)
            ->setParameter('rid', $resourceId, 'uuid')
            ->getQuery()
            ->execute();
    }

    /** Purge les liens révoqués depuis avant $threshold (cf. app:share-link:purge-revoked). */
    public function deleteRevokedOlderThan(\DateTimeImmutable $threshold): int
    {
        return (int) $this->createQueryBuilder('sl')
            ->delete()
            ->where('sl.revokedAt IS NOT NULL')
            ->andWhere('sl.revokedAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }
}
