<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authentifie les appels service-to-service inter-instances du broadcast
 * admin (#283) — pas de compte utilisateur associé, juste un secret partagé
 * identique sur les 7 instances (header X-Broadcast-Token). Distinct du
 * firewall `api` (JWT utilisateur), structurellement incompatible avec ce
 * cas d'usage.
 */
final class BroadcastTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly string $sharedToken,
    ) {}

    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $token = $request->headers->get('X-Broadcast-Token', '');

        if ($this->sharedToken === '' || !hash_equals($this->sharedToken, $token)) {
            throw new CustomUserMessageAuthenticationException('Token de broadcast invalide.');
        }

        return new SelfValidatingPassport(new UserBadge(
            'broadcast-service',
            static fn (): InMemoryUser => new InMemoryUser('broadcast-service', null, ['ROLE_BROADCAST_SERVICE']),
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response('Unauthorized', Response::HTTP_UNAUTHORIZED);
    }
}
