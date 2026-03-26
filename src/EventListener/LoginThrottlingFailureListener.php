<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * Retourne HTTP 429 quand le rate limiter bloque une tentative de connexion.
 * Sans ce listener, le failure_handler Lexik JWT convertit tout en 401.
 */
#[AsEventListener(event: LoginFailureEvent::class, priority: 10)]
final class LoginThrottlingFailureListener
{
    public function __invoke(LoginFailureEvent $event): void
    {
        if (!$event->getException() instanceof TooManyLoginAttemptsAuthenticationException) {
            return;
        }

        $event->setResponse(new JsonResponse(
            ['code' => Response::HTTP_TOO_MANY_REQUESTS, 'message' => 'Too many login attempts. Please try again later.'],
            Response::HTTP_TOO_MANY_REQUESTS
        ));
    }
}
