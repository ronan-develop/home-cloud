<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Interface\UserRepositoryInterface;
use App\Service\GuestAccountCreator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Gestion CRUD des comptes invités (créer/éditer/supprimer).
 *
 * HomeCloud est mono-owner par instance (un seul compte "full") : la liste
 * ne filtre donc pas par propriétaire de l'invitation, seulement par
 * accountType=guest. Toute route ici vérifie explicitement isGuest() sur la
 * cible, pour ne jamais permettre d'éditer/supprimer le compte owner lui-même
 * ou un autre compte complet via cette page.
 */
#[IsGranted('ROLE_USER')]
final class GuestManagementWebController extends AbstractController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly GuestAccountCreator $guestAccountCreator,
    ) {}

    #[Route('/invites', name: 'app_guests')]
    public function index(): Response
    {
        $guests = $this->userRepository->findBy(['accountType' => User::ACCOUNT_TYPE_GUEST], ['createdAt' => 'DESC']);

        return $this->render('web/guests.html.twig', ['guests' => $guests]);
    }

    #[Route('/invites/create', name: 'app_guest_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('guest-create', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $email = trim((string) $request->request->get('email', ''));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->addFlash('error', 'Email invalide.');

            return $this->redirect('/invites');
        }

        if ($this->userRepository->findOneBy(['email' => $email]) !== null) {
            $this->addFlash('error', 'Un compte avec cet email existe déjà.');

            return $this->redirect('/invites');
        }

        $this->guestAccountCreator->create($email);
        $this->addFlash('success', 'Invité créé.');

        return $this->redirect('/invites');
    }

    #[Route('/invites/{id}/edit', name: 'app_guest_edit', methods: ['POST'])]
    public function edit(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('guest-edit', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $guest = $this->findGuestOrFail($id);

        $displayName = trim((string) $request->request->get('displayName', ''));
        if ($displayName !== '') {
            $guest->setDisplayName($displayName);
            $this->em->flush();
            $this->addFlash('success', 'Invité mis à jour.');
        }

        return $this->redirect('/invites');
    }

    #[Route('/invites/{id}/delete', name: 'app_guest_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('guest-delete', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $guest = $this->findGuestOrFail($id);

        $this->em->remove($guest);
        $this->em->flush();

        $this->addFlash('success', 'Invité supprimé.');

        return $this->redirect('/invites');
    }

    private function findGuestOrFail(string $id): User
    {
        $user = $this->userRepository->find(Uuid::fromString($id));

        if ($user === null || !$user->isGuest()) {
            throw $this->createNotFoundException('Invité introuvable.');
        }

        return $user;
    }
}
