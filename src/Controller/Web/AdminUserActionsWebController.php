<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Interface\PasswordResetInitiatorInterface;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use App\Security\AdminVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Actions admin sur un user (#375) : désactivation/réactivation de compte
 * et déclenchement d'un reset password. Réservées à l'admin whitelisté
 * (AdminVoter → BroadcastAdminChecker).
 */
#[IsGranted(AdminVoter::ADMIN)]
final class AdminUserActionsWebController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly EntityManagerInterface $em,
        private readonly PasswordResetInitiatorInterface $passwordResetInitiator,
    ) {}

    #[Route('/admin/users/{id}/deactivate', name: 'app_admin_user_deactivate', methods: ['POST'])]
    public function deactivate(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin-user-deactivate', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $user = $this->findOwnerOrFail($id);
        $user->deactivate();
        $this->refreshTokenRepository->deleteAllForUser($user);
        $this->em->flush();

        $this->addFlash('success', 'Compte désactivé.');

        return $this->redirectToRoute('app_admin_user_show', ['id' => $id]);
    }

    #[Route('/admin/users/{id}/reactivate', name: 'app_admin_user_reactivate', methods: ['POST'])]
    public function reactivate(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin-user-reactivate', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $user = $this->findOwnerOrFail($id);
        $user->reactivate();
        $this->em->flush();

        $this->addFlash('success', 'Compte réactivé.');

        return $this->redirectToRoute('app_admin_user_show', ['id' => $id]);
    }

    #[Route('/admin/users/{id}/reset-password', name: 'app_admin_user_reset_password', methods: ['POST'])]
    public function resetPassword(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin-user-reset-password', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $user = $this->findOwnerOrFail($id);

        // Pas de rate limiting ici (contrairement au flux self-service) :
        // l'admin est déjà authentifié et whitelisté, la limite existe pour
        // se protéger d'un tiers anonyme, pas de ce cas d'usage.
        $this->passwordResetInitiator->sendResetLink($user, $request);

        $this->addFlash('success', 'Lien de réinitialisation envoyé à ' . $user->getEmail() . '.');

        return $this->redirectToRoute('app_admin_user_show', ['id' => $id]);
    }

    private function findOwnerOrFail(string $id): User
    {
        $user = $this->userRepository->findOwnerById(Uuid::fromString($id));
        if ($user === null) {
            throw $this->createNotFoundException();
        }

        return $user;
    }
}
