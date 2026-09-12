<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Snapshot de santé d'une instance HomeCloud pour le monitoring
 * multi-instances (#376) — nb users, stockage total, révision git déployée,
 * joignabilité (toujours true pour l'instance locale, peut être false pour
 * une instance distante injoignable).
 */
final readonly class InstanceMonitoringSnapshot
{
    public function __construct(
        public bool $reachable,
        public int $userCount = 0,
        public int $totalStorageBytes = 0,
        public string $gitRevision = '',
    ) {}
}
