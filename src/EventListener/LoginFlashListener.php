<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Ajoute un flash message lors d'une connexion réussie ou échouée (firewall "web" uniquement).
 */
final class LoginFlashListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}

    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if ($event->getFirewallName() !== 'web') {
            return;
        }

        $user = $event->getAuthenticatedToken()->getUser();
        $name = $user instanceof User ? $user->getDisplayName() : 'vous';

        $this->requestStack->getSession()->getFlashBag()->add(
            'success',
            "Bienvenue, {$name} ! 👋"
        );
    }

    #[AsEventListener]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if ($event->getFirewallName() !== 'web') {
            return;
        }

        $this->requestStack->getSession()->getFlashBag()->add(
            'error',
            'Identifiants incorrects. Veuillez réessayer.'
        );
    }
}
