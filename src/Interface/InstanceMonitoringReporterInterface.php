<?php

declare(strict_types=1);

namespace App\Interface;

use App\Service\InstanceMonitoringSnapshot;

interface InstanceMonitoringReporterInterface
{
    /**
     * Construit le snapshot de santé de l'instance courante (nb users,
     * stockage total, révision git déployée).
     */
    public function getLocalSnapshot(): InstanceMonitoringSnapshot;
}
