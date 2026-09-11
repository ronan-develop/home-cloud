<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Repository\LoginAttemptRepository;
use App\Security\AdminVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Vue admin des tentatives de connexion échouées (#386) — angle mort de
 * sécurité identifié en parallèle du chantier reset-password/rate-limiting
 * (#318). Réservée à l'admin whitelisté (AdminVoter → BroadcastAdminChecker).
 */
#[IsGranted(AdminVoter::ADMIN)]
final class AdminLoginAttemptsWebController extends AbstractController
{
    public function __construct(
        private readonly LoginAttemptRepository $loginAttemptRepository,
    ) {}

    #[Route('/admin/login-attempts', name: 'app_admin_login_attempts', methods: ['GET'])]
    public function __invoke(): Response
    {
        $since = new \DateTimeImmutable(LoginAttemptRepository::SUSPICIOUS_WINDOW);

        return $this->render('admin/login_attempts.html.twig', [
            'recentAttempts' => $this->loginAttemptRepository->findRecent(),
            'suspiciousByEmailHash' => $this->loginAttemptRepository->findSuspiciousByEmailHash($since),
            'suspiciousByIp' => $this->loginAttemptRepository->findSuspiciousByIp($since),
        ]);
    }
}
