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

    // #545 : tous les imports non terminaux d'un utilisateur (pending +
    // scheduled + extracting + processing), affichés sur la page avec
    // reprise/abandon par import (pending) ou juste un état visuel pour les
    // autres (déjà en traitement, pas de re-upload possible). Un import
    // scheduled invisible dans cette liste faisait croire à l'utilisateur
    // qu'il fallait relancer un nouvel import — constaté en conditions
    // réelles le 2026-09-30 (doublon d'upload de 23,6 Go).
    /**
     * @return list<TakeoutImport>
     */
    public function findAllActiveByOwner(User $owner): array
    {
        return $this->createQueryBuilder('ti')
            ->where('ti.owner = :owner')
            ->andWhere('ti.status IN (:statuses)')
            ->setParameter('owner', $owner->getId(), 'uuid')
            ->setParameter('statuses', [
                TakeoutImport::STATUS_PENDING,
                TakeoutImport::STATUS_SCHEDULED,
                TakeoutImport::STATUS_EXTRACTING,
                TakeoutImport::STATUS_PROCESSING,
            ])
            ->orderBy('ti.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function remove(TakeoutImport $import): void
    {
        $em = $this->getEntityManager();
        $em->remove($import);
        $em->flush();
    }

    // #522 : imports complets (ZIP tous uploadés) en attente du prochain
    // cycle nocturne — TakeoutImportStartController marque "scheduled" au
    // lieu de dispatcher immédiatement, pour ne pas démarrer un traitement
    // lourd en pleine journée sous forte charge du mutualisé o2switch.
    /**
     * @return list<TakeoutImport>
     */
    public function findAllScheduled(): array
    {
        return $this->findBy(['status' => TakeoutImport::STATUS_SCHEDULED], ['createdAt' => 'ASC']);
    }

    // #493 : candidats à la purge automatique — un import pending jamais
    // repris au-delà du seuil devient orphelin indéfiniment (ligne base +
    // dossier var/takeout-tmp/<uuid>/, potentiellement plusieurs Go).
    /**
     * @return list<TakeoutImport>
     */
    public function findPendingOlderThan(\DateTimeImmutable $threshold): array
    {
        return $this->createQueryBuilder('ti')
            ->where('ti.status = :status')
            ->andWhere('ti.createdAt < :threshold')
            ->setParameter('status', TakeoutImport::STATUS_PENDING)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }
}
