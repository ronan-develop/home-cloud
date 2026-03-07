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
    // Ensure kernel is always booted for API Platform tests (silences deprecation)
    protected static ?bool $alwaysBootKernel = true;

    protected ?string $testUserEmail = 'alice@example.com';
    protected ?string $testUserPassword = 'password123';
    protected ?Client $client = null;

    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        // DAMA s’occupe du rollback automatique, plus de purge manuelle
    }

    protected function createAuthenticatedClient(string|User|null $user = null): Client
    {
        if (is_string($user)) {
            $email = $user;
            $displayName = 'Test User';
            $userObj = null;
        } else {
            $email = $user ? $user->getEmail() : $this->testUserEmail;
            $displayName = $user ? $user->getDisplayName() : 'Test User';
            $userObj = $user;
        }
        $userEntity = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$userEntity) {
            $userEntity = new User($email, $displayName);
            // Forcer un UUID v4 unique
            $ref = new \ReflectionProperty($userEntity, 'id');
            $ref->setValue($userEntity, \Symfony\Component\Uid\Uuid::v4());
            $userEntity->setPassword($this->testUserPassword);
            $this->em->persist($userEntity);
            $this->em->flush();
        }
        $client = static::createClient([], [
            'headers' => [
                'Authorization' => 'Bearer FAKE_JWT_TOKEN',
                'X-User-Email' => $email,
            ],
        ]);
        return $client;
    }

    protected function createUser(?string $email = null, ?string $password = null, ?string $name = null): User
    {
        $email = $email ?? $this->testUserEmail;
        $password = $password ?? $this->testUserPassword;
        $name = $name ?? 'Test User';
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if (!$user) {
            $user = new User($email, $name);
            // Forcer un UUID v4 unique
            $ref = new \ReflectionProperty($user, 'id');
            $ref->setValue($user, \Symfony\Component\Uid\Uuid::v4());
            $user->setPassword($password);
            $this->em->persist($user);
            $this->em->flush();
        }
        return $user;
    }

    protected function createFolder(string $name, User $user, ?object $parent = null, ?EntityManagerInterface $em = null): object
    {
        $folderClass = 'App\\Entity\\Folder';
        $folder = new $folderClass($name, $user, $parent);
        if ($em === null) {
            // Utilise $this->em si disponible
            if (property_exists($this, 'em') && $this->em instanceof EntityManagerInterface) {
                $em = $this->em;
            } else {
                throw new \InvalidArgumentException('EntityManagerInterface manquant pour createFolder');
            }
        }
        $em->persist($folder);
        $em->flush();
        return $folder;
    }
}
