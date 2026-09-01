<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Repository\FileRepository;
use App\Repository\UserRepository;
use App\Security\AdminVoter;
use App\Service\FileSizeFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page liste des users de l'instance courante (#374), point d'entrée de
 * l'espace admin. Réservée à l'admin whitelisté (AdminVoter →
 * BroadcastAdminChecker) — pas de ROLE_ADMIN Symfony.
 */
#[IsGranted(AdminVoter::ADMIN)]
final class AdminUsersWebController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly FileRepository $fileRepository,
        private readonly FileSizeFormatter $fileSizeFormatter,
    ) {}

    #[Route('/admin/users', name: 'app_admin_users', methods: ['GET'])]
    public function __invoke(): Response
    {
        $users = $this->userRepository->findAllOrderedByCreatedAt();

        $rows = array_map(
            fn (User $user) => [
                'user'    => $user,
                'storage' => $this->fileSizeFormatter->format($this->fileRepository->sumSizeByOwner($user)),
            ],
            $users,
        );

        return $this->render('admin/users.html.twig', [
            'rows' => $rows,
        ]);
    }
}
