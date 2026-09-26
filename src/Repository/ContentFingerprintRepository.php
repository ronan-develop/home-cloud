<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContentFingerprint;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentFingerprint>
 */
class ContentFingerprintRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentFingerprint::class);
    }

    public function save(ContentFingerprint $fingerprint): void
    {
        $em = $this->getEntityManager();
        $em->persist($fingerprint);
        $em->flush();
    }

    public function existsForOwner(User $owner, string $contentHash): bool
    {
        $count = (int) $this->createQueryBuilder('cf')
            ->select('COUNT(cf.id)')
            ->where('cf.owner = :owner')
            ->andWhere('cf.contentHash = :contentHash')
            ->setParameter('owner', $owner->getId(), 'uuid')
            ->setParameter('contentHash', $contentHash)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
