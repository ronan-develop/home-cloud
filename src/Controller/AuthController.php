<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Security\RefreshTokenManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    public function __construct(private UserRepository $users, private UserPasswordHasherInterface $hasher, private JWTTokenManagerInterface $jwtManager, private RefreshTokenManager $rtManager) {}

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '{}', true);
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;
        if (!$username || !$password) {
            return new JsonResponse(['error' => 'invalid_credentials'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->users->findOneBy(['username' => $username]);
        if (!$user) {
            return new JsonResponse(['error' => 'invalid_credentials'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->hasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['error' => 'invalid_credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtManager->create($user);
        $rt = $this->rtManager->create($user);

        return new JsonResponse(['token' => $token, 'refresh_token' => $rt->getToken()]);
    }

    #[Route('/api/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '{}', true);
        $refresh = $data['refresh_token'] ?? null;
        if (!$refresh) {
            return new JsonResponse(['error' => 'missing_refresh_token'], Response::HTTP_BAD_REQUEST);
        }

        $rt = $this->rtManager->findValid($refresh);
        if (!$rt) {
            return new JsonResponse(['error' => 'invalid_refresh_token'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $rt->getUser();
        $token = $this->jwtManager->create($user);
        // rotate refresh token
        $this->rtManager->revoke($rt);
        $newRt = $this->rtManager->create($user);

        return new JsonResponse(['token' => $token, 'refresh_token' => $newRt->getToken()]);
    }
}
