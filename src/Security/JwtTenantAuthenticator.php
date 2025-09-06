<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Psr\Log\LoggerInterface;

/**
 * Minimal authenticator that relies on Lexik to decode/validate the token and
 * expects the decoded payload to be available in Request::attributes['jwt_payload']
 * (we populate that via a small event listener `LexikJwtDecodedListener`).
 */
final class JwtTenantAuthenticator extends AbstractAuthenticator
{
    public function __construct(private ?LoggerInterface $logger = null) {}

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization') || $request->attributes->has('jwt_payload');
    }

    public function authenticate(Request $request): PassportInterface
    {
        $payload = $request->attributes->get('jwt_payload');

        if (!\is_array($payload)) {
            $auth = $request->headers->get('Authorization', '');
            if (\strpos($auth, 'Bearer ') === 0) {
                $token = \substr($auth, 7);
                // If payload not provided by event, we at least carry the raw token for downstream checks
                $payload = ['token' => $token];
            } else {
                $payload = [];
            }
        }

        $userIdentifier = $payload['username'] ?? $payload['sub'] ?? 'anon';

        $passport = new SelfValidatingPassport(new UserBadge($userIdentifier));
        $passport->setAttribute('jwt_payload', $payload);
        return $passport;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // allow request to continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger?->info('Authentication failed: ' . $exception->getMessage());
        return new JsonResponse(['error' => 'Authentication failed'], Response::HTTP_UNAUTHORIZED);
    }
}
