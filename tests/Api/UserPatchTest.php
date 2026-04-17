<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\AuthenticatedApiTestCase;

/**
 * Tests fonctionnels pour PATCH /api/v1/users/{id}.
 */
final class UserPatchTest extends AuthenticatedApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
        $this->createUser('alice@example.com', 'password123', 'Alice');
    }

    /** Le propriétaire peut modifier son displayName → 200 */
    public function testPatchOwnProfileUpdatesDisplayNameReturns200(): void
    {
        $alice = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);

        $client = $this->createAuthenticatedClient($alice);
        $client->request('PATCH', '/api/v1/users/' . $alice->getId(), [
            'json' => ['displayName' => 'Alice Updated'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $client->getResponse()->toArray();
        $this->assertSame('Alice Updated', $data['displayName']);
    }

    /** Le propriétaire peut modifier son email → 200 */
    public function testPatchOwnProfileUpdatesEmailReturns200(): void
    {
        $alice = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);

        $client = $this->createAuthenticatedClient($alice);
        $client->request('PATCH', '/api/v1/users/' . $alice->getId(), [
            'json' => ['email' => 'alice-new@example.com'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $client->getResponse()->toArray();
        $this->assertSame('alice-new@example.com', $data['email']);
    }

    /** Un autre utilisateur ne peut pas modifier le profil → 403 */
    public function testPatchOtherUserProfileForbidden(): void
    {
        $alice = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $bob   = $this->createUser('bob@example.com', 'password123', 'Bob');

        $client = $this->createAuthenticatedClient($bob);
        $client->request('PATCH', '/api/v1/users/' . $alice->getId(), [
            'json' => ['displayName' => 'Hacked'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    /** Utilisateur inexistant → 404 */
    public function testPatchNonExistentUserReturns404(): void
    {
        $alice = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);

        $client = $this->createAuthenticatedClient($alice);
        $client->request('PATCH', '/api/v1/users/00000000-0000-0000-0000-000000000000', [
            'json' => ['displayName' => 'Nobody'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    /** Email invalide → 422 */
    public function testPatchWithInvalidEmailReturns422(): void
    {
        $alice = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);

        $client = $this->createAuthenticatedClient($alice);
        $client->request('PATCH', '/api/v1/users/' . $alice->getId(), [
            'json' => ['email' => 'not-an-email'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    /** Mot de passe trop court → 422 */
    public function testPatchWithShortPasswordReturns422(): void
    {
        $alice = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);

        $client = $this->createAuthenticatedClient($alice);
        $client->request('PATCH', '/api/v1/users/' . $alice->getId(), [
            'json' => ['password' => '123'],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }
}
