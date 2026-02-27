<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels pour l'authentification JWT.
 *
 * Couvre :
 * - POST /api/v1/auth/login → 200 + token (credentials valides)
 * - POST /api/v1/auth/login → 200 + refresh_token (credentials valides)
 * - POST /api/v1/auth/login → 401 (mauvais mot de passe)
 * - POST /api/v1/auth/login → 401 (email inconnu)
 * - GET /api/v1/users → 401 sans token
 * - GET /api/v1/users → 200 avec token valide
 * - POST /api/v1/auth/token/refresh → 200 + nouveau token (refresh_token valide)
 * - POST /api/v1/auth/token/refresh → 401 (refresh_token invalide)
 * - POST /api/v1/auth/token/refresh → 401 (refresh_token expiré)
 */
final class AuthTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $hasher;

    protected function setUp(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM refresh_tokens');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function createUserWithPassword(string $email, string $plainPassword): User
    {
        $user = new User($email, 'Test User');
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function testLoginReturns200WithTokenOnValidCredentials(): void
    {
        $this->createUserWithPassword('alice@example.com', 'password123');

        $response = static::createClient()->request('POST', '/api/v1/auth/login', [
            'json' => [
                'email' => 'alice@example.com',
                'password' => 'password123',
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertArrayHasKey('token', $response->toArray());
    }

    public function testLoginReturns401OnWrongPassword(): void
    {
        $this->createUserWithPassword('alice@example.com', 'password123');

        static::createClient()->request('POST', '/api/v1/auth/login', [
            'json' => [
                'email' => 'alice@example.com',
                'password' => 'wrongpassword',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginReturns401OnUnknownEmail(): void
    {
        static::createClient()->request('POST', '/api/v1/auth/login', [
            'json' => [
                'email' => 'nobody@example.com',
                'password' => 'password123',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testApiReturns401WithoutToken(): void
    {
        static::createClient()->request('GET', '/api/v1/users');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testApiReturns200WithValidToken(): void
    {
        $this->createUserWithPassword('alice@example.com', 'password123');

        $client = static::createClient();

        // Obtenir le token
        $response = $client->request('POST', '/api/v1/auth/login', [
            'json' => [
                'email' => 'alice@example.com',
                'password' => 'password123',
            ],
        ]);
        $token = $response->toArray()['token'];

        // Utiliser le token
        $client->request('GET', '/api/v1/users', [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);

        $this->assertResponseStatusCodeSame(200);
    }

    public function testLoginReturnsRefreshToken(): void
    {
        $this->createUserWithPassword('alice@example.com', 'password123');

        $response = static::createClient()->request('POST', '/api/v1/auth/login', [
            'json' => [
                'email' => 'alice@example.com',
                'password' => 'password123',
            ],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertNotEmpty($data['refresh_token']);
    }

    public function testRefreshReturns200WithNewToken(): void
    {
        $this->createUserWithPassword('alice@example.com', 'password123');

        $client = static::createClient();

        // Login pour obtenir le refresh_token
        $response = $client->request('POST', '/api/v1/auth/login', [
            'json' => ['email' => 'alice@example.com', 'password' => 'password123'],
        ]);
        $refreshToken = $response->toArray()['refresh_token'];

        // Utiliser le refresh_token
        $response = $client->request('POST', '/api/v1/auth/token/refresh', [
            'json' => ['refresh_token' => $refreshToken],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
    }

    public function testRefreshReturns401WithInvalidToken(): void
    {
        static::createClient()->request('POST', '/api/v1/auth/token/refresh', [
            'json' => ['refresh_token' => 'invalid-token-xyz'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testRefreshReturns401WithExpiredToken(): void
    {
        $user = $this->createUserWithPassword('alice@example.com', 'password123');

        // Créer manuellement un refresh_token expiré
        $expired = new RefreshToken($user, new \DateTimeImmutable('-1 second'));
        $this->em->persist($expired);
        $this->em->flush();

        static::createClient()->request('POST', '/api/v1/auth/token/refresh', [
            'json' => ['refresh_token' => $expired->getToken()],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}
