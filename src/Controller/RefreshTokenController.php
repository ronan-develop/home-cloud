<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\RefreshToken;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/v1/auth/token/refresh
 *
 * Échange un refresh_token valide contre un nouveau JWT + un nouveau refresh_token
 * (rotation : l'ancien token est invalidé immédiatement).
 */
final class RefreshTokenController extends AbstractController
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly EntityManagerInterface $em,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {}

    #[Route('/api/v1/auth/token/refresh', name: 'api_auth_token_refresh', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $tokenString = $data['refresh_token'] ?? '';

        if (empty($tokenString)) {
            return new JsonResponse(['message' => 'Missing refresh_token'], Response::HTTP_UNAUTHORIZED);
        }

        $refreshToken = $this->refreshTokenRepository->findValidByToken($tokenString);

        if ($refreshToken === null || $refreshToken->isExpired()) {
            return new JsonResponse(['message' => 'Invalid or expired refresh_token'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $refreshToken->getUser();

        // Rotation : suppression de l'ancien token
        $this->em->remove($refreshToken);

        // Nouveau refresh token
        $newRefreshToken = new RefreshToken($user);
        $this->em->persist($newRefreshToken);
        $this->em->flush();

        // Nouveau JWT
        $jwt = $this->jwtManager->create($user);

        return new JsonResponse([
            'token' => $jwt,
            'refresh_token' => $newRefreshToken->getToken(),
        ]);
    }
}
