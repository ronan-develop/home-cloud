<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\File;
use App\Entity\Media;
use App\Entity\User;
use App\Interface\MediaRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    public function findByFile(File $file): ?Media
    {
        return $this->findOneBy(['file' => $file]);
    }

    /**
     * @param array<string, string> $orderBy Champ => direction ('asc'|'desc'). Même
     *                                        convention que FileRepository::findFiltered() :
     *                                        champs inconnus ignorés silencieusement.
     * @return Media[]
     */
    public function findByOwner(User $user, ?string $type = null, array $orderBy = []): array
    {
        // leftJoin (pas join) : un Media détaché (#246, file NULL) doit rester
        // visible dans sa galerie — filtré directement sur m.owner, qui ne
        // dépend pas de l'existence du File.
        $qb = $this->createQueryBuilder('m')
            ->addSelect('COALESCE(f.createdAt, m.createdAt) AS HIDDEN sortCreatedAt')
            ->leftJoin('m.file', 'f')
            ->where('m.owner = :userId')
            ->setParameter('userId', $user->getId(), 'uuid');

        if ($type !== null) {
            $qb->andWhere('m.mediaType = :type')->setParameter('type', $type);
        }

        // 'takenAt' trie par date de prise de vue (EXIF) : les médias sans date
        // de capture (takenAt NULL) se regroupent à une extrémité selon le SGBD.
        // Un Media détaché n'a plus de f.originalName/size/createdAt (f est
        // NULL) : ces tris le placent à une extrémité selon le SGBD, comme
        // takenAt NULL — comportement accepté, pas de régression du tri lui-même.
        $allowed = ['originalName' => 'f.originalName', 'size' => 'f.size', 'createdAt' => 'f.createdAt', 'takenAt' => 'm.takenAt'];
        $applied = false;
        foreach ($orderBy as $field => $dir) {
            if (isset($allowed[$field])) {
                $qb->addOrderBy($allowed[$field], strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC');
                $applied = true;
            }
        }

        if (!$applied) {
            // sortCreatedAt (HIDDEN, cf. addSelect ci-dessus) : un Media
            // détaché n'a plus de f.createdAt (file NULL), on retombe alors
            // sur sa propre date de création pour garder un tri par défaut
            // cohérent (le plus récent en premier).
            $qb->orderBy('sortCreatedAt', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }
}
