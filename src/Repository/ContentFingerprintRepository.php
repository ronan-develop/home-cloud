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

    /**
     * Vérifie en un seul aller-retour DB lesquels de $hashes existent déjà
     * pour cet owner — indispensable pour un import de masse (#327, Google
     * Takeout) où une requête par fichier serait un goulot d'étranglement
     * sur plusieurs milliers d'entrées.
     *
     * @param string[] $hashes
     * @return string[] Le sous-ensemble de $hashes déjà connu pour cet owner
     */
    public function findExistingHashes(User $owner, array $hashes): array
    {
        if ($hashes === []) {
            return [];
        }

        return array_column(
            $this->createQueryBuilder('cf')
                ->select('cf.contentHash')
                ->where('cf.owner = :owner')
                ->andWhere('cf.contentHash IN (:hashes)')
                ->setParameter('owner', $owner->getId(), 'uuid')
                ->setParameter('hashes', $hashes)
                ->getQuery()
                ->getScalarResult(),
            'contentHash',
        );
    }
}
