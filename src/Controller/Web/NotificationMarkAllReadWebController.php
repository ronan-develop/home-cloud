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

/**
 * "Tout marquer comme lu" (#533) — orchestre les deux mécanismes de lecture
 * déjà en place (lastChangelogViewedAt + DirectMessage.readAt), pas de
 * nouvelle table ni de refonte de la pile virtuelle de notifications (#373).
 */
#[IsGranted('ROLE_USER')]
final class NotificationMarkAllReadWebController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DirectMessageRepository $directMessageRepository,
    ) {}

    #[Route('/notifications/mark-all-read', name: 'app_notifications_mark_all_read', methods: ['POST'])]
    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $user->setLastChangelogViewedAt(new \DateTimeImmutable());
        $this->directMessageRepository->markAllAsReadForRecipient($user);
        $this->em->flush();

        return new Response(status: 204);
    }
}
