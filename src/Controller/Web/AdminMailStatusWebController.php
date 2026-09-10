<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Repository\MessengerMessageRepository;
use App\Security\AdminVoter;
use App\Service\MailerConnectivityChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Statut envoi d'emails dans l'espace admin (#385), suite à #378 où un mot
 * de passe SMTP expiré était resté invisible des semaines faute de
 * diagnostic proactif. Réservée à l'admin whitelisté (AdminVoter →
 * BroadcastAdminChecker) — pas de ROLE_ADMIN Symfony.
 */
#[IsGranted(AdminVoter::ADMIN)]
final class AdminMailStatusWebController extends AbstractController
{
    public function __construct(
        private readonly MailerConnectivityChecker $connectivityChecker,
        private readonly MessengerMessageRepository $messengerMessageRepository,
    ) {}

    #[Route('/admin/mail-status', name: 'app_admin_mail_status', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/mail_status.html.twig', [
            'connectivity'  => $this->connectivityChecker->check(),
            'pendingCount'  => $this->messengerMessageRepository->countPendingMailMessages(),
            'oldestPending' => $this->messengerMessageRepository->findOldestPendingMailMessageAge(),
            'failedCount'   => $this->messengerMessageRepository->countFailedMailMessages(),
        ]);
    }
}
