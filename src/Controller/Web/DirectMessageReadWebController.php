<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Repository\DirectMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Marquage lu explicite d'un message direct (#373) — déclenché au clic sur
 * l'item, découplé de l'affichage de la pile de notifications. Seul le
 * destinataire peut marquer son propre message.
 */
#[IsGranted('ROLE_USER')]
final class DirectMessageReadWebController extends AbstractController
{
    public function __construct(
        private readonly DirectMessageRepository $directMessageRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/direct-messages/{id}/read', name: 'app_direct_message_read', methods: ['POST'])]
    public function __invoke(string $id): Response
    {
        $directMessage = $this->directMessageRepository->find(Uuid::fromString($id));

        if ($directMessage === null) {
            throw $this->createNotFoundException('Message introuvable.');
        }

        /** @var User $user */
        $user = $this->getUser();

        if (!$directMessage->getRecipient()->getId()->equals($user->getId())) {
            throw $this->createAccessDeniedException("Ce message ne vous est pas destiné.");
        }

        $directMessage->markAsRead();
        $this->em->flush();

        return new Response(status: 204);
    }
}
