<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Security\AdminVoter;
use App\Service\InstanceMonitoringOrchestrator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Monitoring multi-instances dans l'espace admin (#376) — une ligne par
 * instance (nb users, stockage total, révision git déployée, joignabilité),
 * sur le modèle du broadcast admin (#283).
 */
#[IsGranted(AdminVoter::ADMIN)]
final class AdminMonitoringWebController extends AbstractController
{
    public function __construct(
        private readonly InstanceMonitoringOrchestrator $orchestrator,
    ) {}

    #[Route('/admin/monitoring', name: 'app_admin_monitoring', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/monitoring.html.twig', [
            'snapshots' => $this->orchestrator->collectAll(),
        ]);
    }
}
