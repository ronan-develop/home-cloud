<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Interface\UserRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<User>
 * @method User|null find(mixed $id, LockMode|int|null $lockMode = null, int|null $lockVersion = null)
 * @method User[] findAll()
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements UserRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function find(mixed $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null): ?User
    {
        return parent::find($id, $lockMode, $lockVersion);
    }

    /** @param array<string, mixed> $criteria */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?User
    {
        return parent::findOneBy($criteria, $orderBy);
    }

    /**
     * Exclut les comptes invités : un admin ne doit pas voir leur identité
     * (email), ils sont là à l'invitation d'un user propriétaire, pas de
     * l'admin de l'instance (cf. #389).
     *
     * @return User[]
     */
    public function findOwnersOrderedByCreatedAt(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.accountType = :accountType')
            ->setParameter('accountType', User::ACCOUNT_TYPE_FULL)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne null pour un compte invité ou un ID inconnu — même garde-fou
     * RGPD que findOwnersOrderedByCreatedAt() (#389/#391), un invité n'est
     * jamais accessible individuellement par l'admin.
     */
    public function findOwnerById(Uuid $id): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.id = :id')
            ->andWhere('u.accountType = :accountType')
            ->setParameter('id', $id->toBinary())
            ->setParameter('accountType', User::ACCOUNT_TYPE_FULL)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
