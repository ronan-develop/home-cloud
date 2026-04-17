<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Entity\Folder;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Tests fonctionnels — tri et recherche sur GET /api/v1/files.
 */
final class FileFilterTest extends AuthenticatedApiTestCase
{
    private \App\Entity\User $alice;
    private Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice  = $this->createUser('alice@filefilter.com', 'password123', 'Alice');
        $this->folder = $this->createFolder('Uploads', $this->alice);

        foreach (['zèbre.txt', 'alpha.txt', 'mango.txt'] as $name) {
            $file = new File($name, 'text/plain', 100, 'uploads/' . $name, $this->folder, $this->alice);
            $this->em->persist($file);
        }
        $this->em->flush();
    }

    /** ?order[originalName]=asc → ordre alphabétique */
    public function testGetFilesOrderByNameAsc(): void
    {
        $client = $this->createAuthenticatedClient($this->alice);
        $response = $client->request('GET', '/api/v1/files?order[originalName]=asc&folderId=' . $this->folder->getId(), [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $names = array_column($data, 'originalName');
        $sorted = $names;
        sort($sorted);
        $this->assertSame($sorted, $names);
    }

    /** ?order[originalName]=desc → ordre inverse */
    public function testGetFilesOrderByNameDesc(): void
    {
        $client = $this->createAuthenticatedClient($this->alice);
        $response = $client->request('GET', '/api/v1/files?order[originalName]=desc&folderId=' . $this->folder->getId(), [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $names = array_column($data, 'originalName');
        $sorted = $names;
        rsort($sorted);
        $this->assertSame($sorted, $names);
    }

    /** ?originalName=ang → retourne mango.txt */
    public function testGetFilesSearchByName(): void
    {
        $client = $this->createAuthenticatedClient($this->alice);
        $response = $client->request('GET', '/api/v1/files?originalName=ang', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $names = array_column($data, 'originalName');
        $this->assertContains('mango.txt', $names);
        $this->assertNotContains('alpha.txt', $names);
    }

    /** ?originalName=xyz → résultat vide */
    public function testGetFilesSearchNoMatch(): void
    {
        $client = $this->createAuthenticatedClient($this->alice);
        $response = $client->request('GET', '/api/v1/files?originalName=xyz', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertEmpty($data);
    }
}
