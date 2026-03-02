<?php

declare(strict_types=1);

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as BaseApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
    private ?User $authenticatedUser = null;

    /**
     * Effectue un login API et retourne le JWT pour l'email/mot de passe donnés.
     */
    protected function getAuthToken(string $email, string $password = 'password123'): string
    {
        $client = static::createClient();
        $response = $client->request('POST', '/api/v1/auth/login', [
            'json' => [
                'email' => $email,
                'password' => $password,
            ],
        ]);
        $data = $response->toArray(false);
        if (!isset($data['token'])) {
            throw new \RuntimeException('JWT non retourné par /api/v1/auth/login pour ' . $email);
        }
        return $data['token'];
    }

    // ...existing code...
    /**
     * Force l'authentification d'un utilisateur dans le TokenStorage
     */
    protected function authenticateUser(User $user): void
    {
        $tokenStorage = static::getContainer()->get(TokenStorageInterface::class);
        $token = new UsernamePasswordToken(
            $user,
            'api', // Nom du firewall
            $user->getRoles()
        );
        $tokenStorage->setToken($token);
        $this->authenticatedUser = $user;
    }

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

        // Invalide le cache du token JWT pour garantir la cohérence User/token
        $this->cachedToken = null;

        return $user;
    }

    // (Suppression du bloc erroné hors méthode)

    /**
     * Retourne un Client pré-configuré avec le header Authorization JWT.
     * Accepte un User ou un email (string).
     */
    protected function createAuthenticatedClient(
        User|string|null $userOrEmail = null,
        string $password = 'password123',
    ): Client {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        if ($userOrEmail instanceof User) {
            $email = $userOrEmail->getEmail();
        } elseif (is_string($userOrEmail)) {
            $email = $userOrEmail;
        } else {
            $email = 'alice@example.com';
        }
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            throw new \RuntimeException("User with email $email not found");
        }

        // Forcer l'authentification dans le TokenStorage
        $this->authenticateUser($user);

        // Récupérer le token JWT
        $token = $this->getAuthToken($email, $password);

        // Créer le client avec le header Authorization
        return static::createClient([], [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ],
        ]);
    }
    /**
     * Récupère l'utilisateur actuellement authentifié
     */
    protected function getAuthenticatedUser(): ?User
    {
        return $this->authenticatedUser;
    }

    protected function tearDown(): void
    {
        $this->cachedToken = null;
        $this->authenticatedUser = null;
        // Nettoyer le TokenStorage
        $tokenStorage = static::getContainer()->get(TokenStorageInterface::class);
        $tokenStorage->setToken(null);
        parent::tearDown();
    }
}
