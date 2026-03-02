<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Media;
use App\Entity\User;
use App\Interface\MediaRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Media>
 * @method Media[] findAll()
 * @method Media|null findOneBy(array $criteria, array $orderBy = null)
 * @method Media[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MediaRepository extends ServiceEntityRepository implements MediaRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    public function findById(Uuid $id): ?Media
    {
        return $this->find($id);
    }

    /** @return Media[] */
    public function findByOwner(User $user, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->join('m.file', 'f')
            ->join('f.owner', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $user->getId(), 'uuid')
            ->orderBy('f.createdAt', 'DESC');

        if ($type !== null) {
            $qb->andWhere('m.mediaType = :type')->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }
}
