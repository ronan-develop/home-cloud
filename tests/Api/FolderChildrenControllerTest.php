<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\AuthenticatedApiTestCase;

/**
 * Tests fonctionnels — GET /api/v1/folders/{id}/children.
 */
final class FolderChildrenControllerTest extends AuthenticatedApiTestCase
{
    private \App\Entity\User $alice;
    private \App\Entity\User $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = $this->createUser('alice@children.com', 'password123', 'Alice');
        $this->bob = $this->createUser('bob@children.com', 'password123', 'Bob');
    }

    public function testOwnerGetsChildren(): void
    {
        $folder = $this->createFolder('Racine', $this->alice);
        $this->createFolder('Sous-dossier', $this->alice, $folder);

        $client = $this->createAuthenticatedClient($this->alice);
        $response = $client->request('GET', '/api/v1/folders/' . $folder->getId() . '/children', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertCount(1, $response->toArray()['items']);
    }

    public function testNonOwnerReceives403(): void
    {
        $folder = $this->createFolder('Racine', $this->alice);

        $client = $this->createAuthenticatedClient($this->bob);
        $client->request('GET', '/api/v1/folders/' . $folder->getId() . '/children', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testNonExistentFolderReceives403(): void
    {
        $client = $this->createAuthenticatedClient($this->alice);
        $client->request('GET', '/api/v1/folders/' . \Symfony\Component\Uid\Uuid::v7() . '/children', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
