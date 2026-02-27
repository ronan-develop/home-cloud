<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;

/**
 * Génère et attache un refresh_token à chaque réponse de login JWT réussie.
 *
 * Déclenché par l'événement `lexik_jwt_authentication.on_authentication_success`
 * (success_handler configuré dans security.yaml).
 */
final class AuthenticationSuccessListener
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $refreshToken = new RefreshToken($user);
        $this->em->persist($refreshToken);
        $this->em->flush();

        $data = $event->getData();
        $data['refresh_token'] = $refreshToken->getToken();
        $event->setData($data);
    }
}
