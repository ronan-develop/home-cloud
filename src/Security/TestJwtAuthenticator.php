<?php

namespace App\Security;

use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use App\Entity\User;

class TestJwtAuthenticator extends AbstractAuthenticator
{
    private \App\Repository\UserRepository $userRepository;

    public function __construct(\App\Repository\UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function supports(Request $request): ?bool
    {
        // Active uniquement en environnement de test
        return $_ENV['APP_ENV'] === 'test' && $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader === 'Bearer FAKE_JWT_TOKEN') {
            $email = $request->headers->get('X-User-Email', 'test@homecloud.local');
            return new SelfValidatingPassport(new UserBadge($email, function ($email) {
                $user = $this->userRepository->findOneBy(['email' => $email]);
                if ($user) {
                    return $user;
                }
                // Si l’utilisateur n’existe pas, le créer (optionnel, selon stratégie)
                $displayName = explode('@', $email)[0];
                $user = new User($email, ucfirst($displayName));
                // Pas de persist ici, juste pour tests
                return $user;
            }));
        }
        throw new AuthenticationException('Invalid test JWT token');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new Response('Authentication Failed', Response::HTTP_UNAUTHORIZED);
    }
}
