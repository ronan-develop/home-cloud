<?php

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Contrôleur d’accueil de l’API Home Cloud.
 *
 * Ce contrôleur expose le endpoint GET /api (non documenté dans Swagger/OpenAPI)
 * pour fournir un message d’accueil, la version de l’API et l’URL du login.
 *
 * ⚠️ Pour un endpoint d’accueil documenté dans Swagger, utiliser la ressource InfoApiOutput (DTO + provider) exposée via API Platform.
 *
 * @see App\Dto\InfoApiOutput pour la version documentée et maintenable.
 */
#[Route('/api')]
final class ApiHomeController extends AbstractController
{
    #[Route('', name: 'api_root', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Bienvenue sur l’API Home Cloud.',
            'version' => '1.0.0',
            'login_endpoint' => '/api/login',
            'info' => 'Authentifiez-vous via POST /api/login avec vos credentials (email/username + password).'
        ]);
    }
}
