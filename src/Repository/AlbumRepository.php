<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Album;
use App\Entity\User;
use App\Interface\AlbumRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Album>
 * @method Album[] findAll()
 * @method Album|null findOneBy(array $criteria, array $orderBy = null)
 * @method Album[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AlbumRepository extends ServiceEntityRepository implements AlbumRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Album::class);
    }

    public function findById(Uuid $id): ?Album
    {
        return $this->find($id);
    }

    public function save(Album $album): void
    {
        $this->getEntityManager()->persist($album);
        $this->getEntityManager()->flush();
    }

    public function remove(Album $album): void
    {
        $this->getEntityManager()->remove($album);
        $this->getEntityManager()->flush();
    }

    /** @return Album[] */
    public function findByOwner(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.owner', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $user->getId(), 'uuid')
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
