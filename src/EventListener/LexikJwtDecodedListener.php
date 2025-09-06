<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Copies the decoded JWT payload from Lexik event into the current Request attributes.
 * This allows authenticators / voters to read the payload via $request->attributes->get('jwt_payload').
 */
final class LexikJwtDecodedListener
{
    public function __construct(private RequestStack $requests) {}

    public function onJwtDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();

        $request = $this->requests->getCurrentRequest();
        if (null === $request) {
            return;
        }

        // store payload under a canonical key
        $request->attributes->set('jwt_payload', $payload);
    }
}
