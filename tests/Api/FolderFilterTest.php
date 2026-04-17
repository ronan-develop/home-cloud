<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\AuthenticatedApiTestCase;

/**
 * Tests fonctionnels — tri et recherche sur GET /api/v1/folders.
 */
final class FolderFilterTest extends AuthenticatedApiTestCase
{
    private \App\Entity\User $alice;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = $this->createUser('alice@filter.com', 'password123', 'Alice');
        $this->createFolder('Banane', $this->alice);
        $this->createFolder('Abricot', $this->alice);
        $this->createFolder('Cerise', $this->alice);
    }

    /** ?order[name]=asc → ordre alphabétique */
    public function testGetFoldersOrderByNameAsc(): void
    {
        $client = $this->createAuthenticatedClient($this->alice);
        $response = $client->request('GET', '/api/v1/folders?order[name]=asc', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $names = array_column($data, 'name');
        $this->assertSame(['Abricot', 'Banane', 'Cerise'], $names);
    }

    /** ?order[name]=desc → ordre alphabétique inversé */
    public function testGetFoldersOrderByNameDesc(): void
    {
        $client = $this->createAuthenticatedClient($this->alice);
        $response = $client->request('GET', '/api/v1/folders?order[name]=desc', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $names = array_column($data, 'name');
        $this->assertSame(['Cerise', 'Banane', 'Abricot'], $names);
    }

    /** ?name=an → retourne Banane (contient "an") */
    public function testGetFoldersSearchByName(): void
    {
        $client = $this->createAuthenticatedClient($this->alice);
        $response = $client->request('GET', '/api/v1/folders?name=an', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $names = array_column($data, 'name');
        $this->assertContains('Banane', $names);
        $this->assertNotContains('Abricot', $names);
        $this->assertNotContains('Cerise', $names);
    }

    /** ?name=xyz → résultat vide */
    public function testGetFoldersSearchNoMatch(): void
    {
        $client = $this->createAuthenticatedClient($this->alice);
        $response = $client->request('GET', '/api/v1/folders?name=xyz', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertEmpty($data);
    }
}
