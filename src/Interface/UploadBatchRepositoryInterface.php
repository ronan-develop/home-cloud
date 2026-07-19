<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\UploadBatch;
use Symfony\Component\Uid\Uuid;

/**
 * Contrat d'accès aux données UploadBatch (DIP : mockable en test, implémentation
 * swappable sans toucher aux consommateurs — controller, handler).
 */
interface UploadBatchRepositoryInterface
{
    public function findById(Uuid $id): ?UploadBatch;

    /**
     * Nombre de fichiers du lot ayant déjà un Media (traitement terminé).
     * Base de la détection de fin de lot, robuste aux ré-exécutions du worker
     * (on compte l'état réel plutôt qu'un compteur incrémental).
     */
    public function countProcessed(UploadBatch $batch): int;
}
