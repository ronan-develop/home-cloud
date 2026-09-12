<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Security\AdminVoter;
use App\Service\CronHealthReporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Santé du cron Messenger de l'instance courante dans l'espace admin (#377)
 * — périmètre recadré en commentaire du ticket : pas de stockage (déjà
 * couvert par #374/#376), pas de vue multi-instances (#376 non fait), pas
 * d'erreurs applicatives (aucun canal de log exploitable actuellement).
 */
#[IsGranted(AdminVoter::ADMIN)]
final class AdminCronHealthWebController extends AbstractController
{
    public function __construct(
        private readonly CronHealthReporter $cronHealthReporter,
    ) {}

    #[Route('/admin/cron-health', name: 'app_admin_cron_health', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/cron_health.html.twig', [
            'report' => $this->cronHealthReporter->getReport(),
        ]);
    }
}
