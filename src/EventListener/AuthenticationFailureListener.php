<?php

declare(strict_types=1);

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Journalise chaque tentative de connexion échouée.
 *
 * Enregistre : email tenté, IP client, user-agent, type d'exception.
 * Permet de détecter a posteriori les attaques brute-force ou par dictionnaire.
 */
#[AsEventListener(event: LoginFailureEvent::class, priority: 0)]
final class AuthenticationFailureListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();

        $this->logger->warning('Authentication failure', [
            'email' => $request->getPayload()->getString('email'),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'exception' => $event->getException()->getMessageKey(),
        ]);
    }
}
