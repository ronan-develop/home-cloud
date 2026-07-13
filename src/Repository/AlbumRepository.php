<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Album;
use App\Entity\AlbumMedia;
use App\Entity\User;
use App\Interface\AlbumRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    /**
     * @param Uuid[] $mediaIds
     * @return array<string, string[]>
     */
    public function findAlbumNamesByMediaIds(array $mediaIds): array
    {
        if ($mediaIds === []) {
            return [];
        }

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('am', 'a.name AS albumName')
            ->from(AlbumMedia::class, 'am')
            ->join('am.album', 'a')
            ->orderBy('a.createdAt', 'ASC');

        $orConditions = [];
        foreach ($mediaIds as $i => $mediaId) {
            $orConditions[] = "am.media = :mediaId{$i}";
            $qb->setParameter("mediaId{$i}", $mediaId, 'uuid');
        }
        $qb->andWhere(implode(' OR ', $orConditions));

        $rows = $qb->getQuery()->getResult();

        $namesByMediaId = [];
        foreach ($rows as $row) {
            $mediaId = $row[0]->getMedia()->getId()->toRfc4122();
            $namesByMediaId[$mediaId][] = $row['albumName'];
        }

        return $namesByMediaId;
    }
}
