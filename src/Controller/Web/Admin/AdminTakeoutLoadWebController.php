<?php

declare(strict_types=1);

namespace App\Controller\Web\Admin;

use App\Entity\TakeoutDispatchLog;
use App\Repository\TakeoutDispatchLogRepository;
use App\Repository\TakeoutImportRepository;
use App\Security\AdminVoter;
use App\Service\Takeout\ServerLoadChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Charge serveur et imports Takeout différés dans l'espace admin (#528) :
 * visibilité sur le mécanisme de dispatch (#522/#524), sans le modifier.
 * La mesure passe par ServerLoadChecker — pas de seconde lecture de
 * /proc/loadavg.
 */
#[IsGranted(AdminVoter::ADMIN)]
final class AdminTakeoutLoadWebController extends AbstractController
{
    private const HISTORY_LIMIT = 50;

    public function __construct(
        private readonly ServerLoadChecker $loadChecker,
        private readonly TakeoutImportRepository $importRepository,
        private readonly TakeoutDispatchLogRepository $dispatchLogRepository,
    ) {}

    #[Route('/admin/takeout-load', name: 'app_admin_takeout_load', methods: ['GET'])]
    public function __invoke(): Response
    {
        $loadAverage = $this->loadChecker->getLoadAverage();

        $counts = $this->dispatchLogRepository->countByOutcomeSince(new \DateTimeImmutable('-24 hours'));
        $cycles = array_sum($counts);
        // Part de cycles où le serveur était assez calme pour dispatcher
        // (dispatched + idle) : l'indicateur qui aurait révélé le 0 % de #543.
        $calmRate = $cycles > 0
            ? (int) round(($counts[TakeoutDispatchLog::OUTCOME_DISPATCHED] + $counts[TakeoutDispatchLog::OUTCOME_IDLE]) / $cycles * 100)
            : null;

        return $this->render('admin/takeout_load.html.twig', [
            'loadAverage' => $loadAverage,
            'threshold' => $this->loadChecker->getThreshold(),
            'isCalm' => $this->loadChecker->isCalm($loadAverage),
            'scheduledImports' => $this->importRepository->findAllScheduled(),
            'recentLogs' => $this->dispatchLogRepository->findRecent(self::HISTORY_LIMIT),
            'cycles24h' => $cycles,
            'calmRate24h' => $calmRate,
        ]);
    }
}
