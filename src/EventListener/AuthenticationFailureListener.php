<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\LoginAttempt;
use App\Repository\LoginAttemptRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Journalise chaque tentative de connexion échouée et la persiste (#386) pour
 * la vue admin de détection des attaques brute-force/dictionnaire.
 *
 * Le log garde le hash tronqué à 12 caractères (comportement #392 inchangé) ;
 * la persistance utilise le hash SHA-256 complet pour un regroupement fiable
 * par compte visé, sans conserver l'email en clair.
 */
#[AsEventListener(event: LoginFailureEvent::class, priority: 0)]
final class AuthenticationFailureListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly LoginAttemptRepository $loginAttemptRepository,
    ) {
    }

    public function __invoke(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $email = $request->getPayload()->getString('email');
        $ip = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');

        $this->logger->warning('Authentication failure', [
            'email_hash' => substr(hash('sha256', $email), 0, 12),
            'ip' => $ip,
            'user_agent' => $userAgent,
            'exception' => $event->getException()->getMessageKey(),
        ]);

        try {
            $this->loginAttemptRepository->save(new LoginAttempt(hash('sha256', $email), $ip, $userAgent));
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to persist login attempt', ['exception' => $e->getMessage()]);
        }
    }
}
