<?php

declare(strict_types=1);

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as BaseApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Classe de base pour tous les tests API.
 * Fournit les helpers :
 * - createUser() — crée un utilisateur en base
 * - createAuthenticatedClient() — retourne un Client avec Authorization header JWT
 * - createFolder() — crée un dossier avec UUID v4
 */
abstract class AuthenticatedApiTestCase extends BaseApiTestCase
{
    protected ?string $testUserEmail = 'alice@example.com';
    protected ?string $testUserPassword = 'password123';
    protected ?Client $client = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->client = static::createClient();
    }

    protected function createAuthenticatedClient(): Client
    {
        // Crée un utilisateur si non existant
        $em = static::getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $this->testUserEmail]);
        if (!$user) {
            $user = new User($this->testUserEmail, 'Test User');
            $user->setPassword($this->testUserPassword); // à adapter selon hash
            $em->persist($user);
            $em->flush();
        }
        // Authentifie le client (exemple JWT, à adapter selon ton projet)
        $this->client = static::createClient();
        // $this->client->setServerParameter('HTTP_Authorization', 'Bearer ' . $jwtToken); // si JWT
        return $this->client;
    }

    protected function createUser(string $email = null, string $password = null, string $name = null): User
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $email = $email ?? $this->testUserEmail;
        $password = $password ?? $this->testUserPassword;
        $name = $name ?? 'Test User';
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $user = new User($email, $name);
            $user->setPassword($password); // à adapter selon hash
            $em->persist($user);
            $em->flush();
        }
        return $user;
    }

    protected function createFolder(string $name, User $user, ?object $parent, EntityManagerInterface $em): object
    {
        $folderClass = 'App\\Entity\\Folder';
        $folder = new $folderClass($name, $user, $parent);
        $em->persist($folder);
        $em->flush();
        return $folder;
    }
}
