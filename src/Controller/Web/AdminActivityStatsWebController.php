<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Repository\AlbumRepository;
use App\Repository\FileRepository;
use App\Repository\ShareLinkRepository;
use App\Repository\ShareRepository;
use App\Repository\UserRepository;
use App\Security\AdminVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Statistiques d'activité de l'instance dans l'espace admin (#388) — compteurs
 * agrégés uniquement. L'admin ne doit jamais voir l'identité des invités (nom,
 * email) au travers de cet écran : un invité est là à l'invitation d'un user
 * propriétaire, pas de l'admin de l'instance (même contrainte que #389/#391).
 */
#[IsGranted(AdminVoter::ADMIN)]
final class AdminActivityStatsWebController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly FileRepository $fileRepository,
        private readonly AlbumRepository $albumRepository,
        private readonly ShareRepository $shareRepository,
        private readonly ShareLinkRepository $shareLinkRepository,
    ) {}

    #[Route('/admin/activity-stats', name: 'app_admin_activity_stats', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/activity_stats.html.twig', [
            'guestCount'       => $this->userRepository->countGuests(),
            'fileCount'        => $this->fileRepository->count([]),
            'albumCount'       => $this->albumRepository->count([]),
            'activeShareCount' => $this->shareRepository->countActive(),
            'activeLinkCount'  => $this->shareLinkRepository->countActive(),
        ]);
    }
}
