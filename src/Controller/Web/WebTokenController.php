<?php

declare(strict_types=1);

namespace App\Controller\Web;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Émet un JWT court (15 min) pour les appels fetch API depuis le frontend web.
 *
 * Le frontend est authentifié par session (firewall "web"). Ce endpoint
 * permet au JS de la page d'obtenir un token pour appeler l'API REST sans
 * stocker de credentials.
 *
 * Le token est à conserver uniquement en mémoire JS (jamais localStorage).
 */
#[IsGranted('ROLE_USER')]
final class WebTokenController extends AbstractController
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {}

    #[Route('/web/token', name: 'app_web_token', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();

        // TTL court : 15 minutes, suffisant pour une session interactive
        $token = $this->jwtManager->createFromPayload($user, [
            'exp' => time() + 900,
        ]);

        return $this->json(['token' => $token]);
    }
}
