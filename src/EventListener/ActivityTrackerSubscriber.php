<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Interface\ActivityTrackerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Trace l'activité de l'instance à chaque requête authentifiée par un
 * compte applicatif (#422) — sert de base à la détection "instance vide"
 * pour le déploiement nocturne (#421).
 */
final readonly class ActivityTrackerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityTrackerInterface $activityTracker,
        private TokenStorageInterface $tokenStorage,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', -100]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null || !$token->getUser() instanceof User) {
            return;
        }

        $this->activityTracker->recordActivity();
    }
}
