<?php

declare(strict_types=1);

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as BaseApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Classe de base pour tous les tests API.
 *
 * Fournit :
 * - createUser() — crée un User en base avec mot de passe haché
 * - createAuthenticatedClient() — retourne un Client avec Authorization header JWT
 */
abstract class AuthenticatedApiTestCase extends BaseApiTestCase
{
    private ?string $cachedToken = null;

    /**
     * Crée un utilisateur avec un mot de passe haché et le persiste en base.
     */
    protected function createUser(
        string $email = 'alice@example.com',
        string $plainPassword = 'password123',
        string $displayName = 'Alice',
    ): User {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User($email, $displayName);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));
        $em->persist($user);
        $em->flush();

        return $user;
    }

    /**
     * Retourne un token JWT valide pour alice@example.com.
     * Le token est mis en cache pour la durée du test (évite N appels login).
     */
    protected function getAuthToken(
        string $email = 'alice@example.com',
        string $password = 'password123',
    ): string {
        if ($this->cachedToken !== null) {
            return $this->cachedToken;
        }

        $response = static::createClient()->request('POST', '/api/v1/auth/login', [
            'json' => compact('email', 'password'),
        ]);

        $this->cachedToken = $response->toArray(throw: false)['token'] ?? '';

        return $this->cachedToken;
    }

    /**
     * Retourne un Client pré-configuré avec le header Authorization JWT.
     */
    protected function createAuthenticatedClient(
        string $email = 'alice@example.com',
        string $password = 'password123',
    ): Client {
        $token = $this->getAuthToken($email, $password);

        return static::createClient([], [
            'headers' => ['Authorization' => 'Bearer '.$token],
        ]);
    }

    protected function tearDown(): void
    {
        $this->cachedToken = null;
        parent::tearDown();
    }
}
