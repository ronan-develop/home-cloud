<?php

declare(strict_types=1);

namespace App\Interface\Monitoring;

use App\Service\Monitoring\InstanceMonitoringSnapshot;

interface InstanceMonitoringReporterInterface
{
    /**
     * Construit le snapshot de santé de l'instance courante (nb users,
     * stockage total, révision git déployée).
     */
    public function getLocalSnapshot(): InstanceMonitoringSnapshot;
}
