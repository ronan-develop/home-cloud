<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShareLink;
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
}
