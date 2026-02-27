<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Folder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class FolderTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createUser(): User
    {
        $user = new User('owner@example.com', 'Owner');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    // --- GET /api/v1/folders/{id} ---

    public function testGetFolderReturns200WithCorrectStructure(): void
    {
        $owner = $this->createUser();
        $folder = new Folder('Documents', $owner);
        $this->em->persist($folder);
        $this->em->flush();

        $response = static::createClient()->request('GET', '/api/v1/folders/'.$folder->getId());

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Documents', $data['name']);
        $this->assertArrayHasKey('parentId', $data);
        $this->assertArrayHasKey('ownerId', $data);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertNull($data['parentId']);
    }

    public function testGetFolderReturns404WhenNotFound(): void
    {
        static::createClient()->request('GET', '/api/v1/folders/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(404);
    }

    // --- GET /api/v1/folders ---

    public function testGetCollectionReturnsFolders(): void
    {
        $owner = $this->createUser();
        $this->em->persist(new Folder('Photos', $owner));
        $this->em->persist(new Folder('Videos', $owner));
        $this->em->flush();

        $response = static::createClient()->request('GET', '/api/v1/folders', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    // --- POST /api/v1/folders ---

    public function testPostFolderCreates201(): void
    {
        $owner = $this->createUser();

        $response = static::createClient()->request('POST', '/api/v1/folders', [
            'json' => [
                'name' => 'Music',
                'ownerId' => (string) $owner->getId(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('Music', $data['name']);
        $this->assertArrayHasKey('id', $data);
        $this->assertNull($data['parentId']);
    }

    public function testPostFolderWithParentCreates201(): void
    {
        $owner = $this->createUser();
        $parent = new Folder('Root', $owner);
        $this->em->persist($parent);
        $this->em->flush();

        $response = static::createClient()->request('POST', '/api/v1/folders', [
            'json' => [
                'name' => 'SubFolder',
                'ownerId' => (string) $owner->getId(),
                'parentId' => (string) $parent->getId(),
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame((string) $parent->getId(), $data['parentId']);
    }

    public function testPostFolderReturns400WhenNameIsMissing(): void
    {
        $owner = $this->createUser();

        static::createClient()->request('POST', '/api/v1/folders', [
            'json' => ['ownerId' => (string) $owner->getId()],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // --- PATCH /api/v1/folders/{id} ---

    public function testPatchFolderUpdatesName(): void
    {
        $owner = $this->createUser();
        $folder = new Folder('OldName', $owner);
        $this->em->persist($folder);
        $this->em->flush();

        $response = static::createClient()->request('PATCH', '/api/v1/folders/'.$folder->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'NewName'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('NewName', $response->toArray()['name']);
    }

    // --- DELETE /api/v1/folders/{id} ---

    public function testDeleteFolderReturns204(): void
    {
        $owner = $this->createUser();
        $folder = new Folder('ToDelete', $owner);
        $this->em->persist($folder);
        $this->em->flush();

        static::createClient()->request('DELETE', '/api/v1/folders/'.$folder->getId());

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteFolderReturns404WhenNotFound(): void
    {
        static::createClient()->request('DELETE', '/api/v1/folders/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(404);
    }
}
