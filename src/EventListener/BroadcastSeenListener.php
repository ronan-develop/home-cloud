<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Interface\BroadcastMessageRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Marque le broadcast in-app (#361, suite de #283) comme vu par
 * l'utilisateur courant, une fois la réponse HTML rendue.
 *
 * Sur KernelEvents::RESPONSE, et pas kernel.controller : le rendu Twig a
 * lieu dans le contrôleur, AVANT cet événement — la bannière affichée dans
 * la réponse courante voit donc encore l'état "non lu" ; seul le flush ici,
 * après coup, fait que la requête SUIVANTE ne la montre plus (dismiss auto
 * au prochain login/à la prochaine page, cf. #361).
 *
 * Exclusion /api et /internal : ces routes ne rendent aucune bannière (JSON
 * pur), et /internal n'a de toute façon pas d'utilisateur authentifié
 * (firewall broadcast_internal, secret partagé). Les marquer "vu" serait à
 * la fois inutile et incorrect (l'utilisateur n'a rien vu).
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class BroadcastSeenListener
{
    public function __construct(
        private readonly Security $security,
        private readonly BroadcastMessageRepositoryInterface $broadcastMessageRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (str_starts_with($path, '/api') || str_starts_with($path, '/internal')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $latest = $this->broadcastMessageRepository->findLatest();
        if ($latest === null) {
            return;
        }

        $seenAt = $user->getLastBroadcastSeenAt();
        if ($seenAt !== null && $seenAt >= $latest->getCreatedAt()) {
            return;
        }

        $user->setLastBroadcastSeenAt(new \DateTimeImmutable());
        $this->em->flush();
    }
}
