<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TakeoutImport;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TakeoutImport>
 */
class TakeoutImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TakeoutImport::class);
    }

    public function save(TakeoutImport $import): void
    {
        $em = $this->getEntityManager();
        $em->persist($import);
        $em->flush();
    }

    // #481 : retrouve l'import interrompu (fermeture d'onglet en cours
    // d'upload) le plus récent d'un utilisateur, pour permettre au front de
    // reprendre au lieu de recréer un import neuf systématiquement.
    public function findLatestPendingByOwner(User $owner): ?TakeoutImport
    {
        return $this->findOneBy(
            ['owner' => $owner, 'status' => TakeoutImport::STATUS_PENDING],
            ['createdAt' => 'DESC'],
        );
    }
}
