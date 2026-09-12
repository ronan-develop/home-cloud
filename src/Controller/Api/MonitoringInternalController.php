<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Interface\InstanceMonitoringReporterInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoint interne inter-instances du monitoring admin (#376). Appelé par
 * l'instance ronan.lenouvel.me sur chaque autre instance pour y lire son
 * snapshot local (DB isolée par instance). Authentification par secret
 * partagé via BroadcastTokenAuthenticator, hors du firewall JWT `api` —
 * même mécanisme que /internal/broadcast (#283).
 */
final class MonitoringInternalController extends AbstractController
{
    public function __construct(
        private readonly InstanceMonitoringReporterInterface $reporter,
    ) {}

    #[Route('/internal/monitoring', name: 'internal_monitoring', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $snapshot = $this->reporter->getLocalSnapshot();

        return new JsonResponse([
            'userCount'         => $snapshot->userCount,
            'totalStorageBytes' => $snapshot->totalStorageBytes,
            'gitRevision'       => $snapshot->gitRevision,
        ]);
    }
}
