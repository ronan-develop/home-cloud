<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Tests fonctionnels pour DELETE /api/v1/files/{id}.
 */
final class FileDeleteTest extends AuthenticatedApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
        $this->createUser('alice@example.com', 'password123', 'Alice');
    }

    private function createFile(string $name, \App\Entity\Folder $folder, \App\Entity\User $owner): File
    {
        $file = new File($name, 'text/plain', 42, 'test/' . uniqid() . '.txt', $folder, $owner, false);
        $this->em->persist($file);
        $this->em->flush();
        return $file;
    }

    /** Le propriétaire peut supprimer son fichier → 204 */
    public function testDeleteFileByOwnerReturns204(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $folder = $this->createFolder('Docs', $alice);
        $file   = $this->createFile('document.txt', $folder, $alice);

        $client = $this->createAuthenticatedClient($alice);
        $client->request('DELETE', '/api/v1/files/' . $file->getId());

        static::assertResponseStatusCodeSame(204);

        $this->em->clear();
        $deleted = $this->em->getRepository(File::class)->find($file->getId());
        $this->assertNull($deleted, 'Le fichier doit être supprimé de la base');
    }

    /** Un autre utilisateur ne peut pas supprimer le fichier → 403 */
    public function testDeleteFileByOtherUserForbidden(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $bob    = $this->createUser('bob@example.com', 'password123', 'Bob');
        $folder = $this->createFolder('AliceFolder', $alice);
        $file   = $this->createFile('secret.txt', $folder, $alice);

        $client = $this->createAuthenticatedClient($bob);
        $client->request('DELETE', '/api/v1/files/' . $file->getId());

        static::assertResponseStatusCodeSame(403);
    }

    /** Fichier inexistant → 404 */
    public function testDeleteNonExistentFileReturns404(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $client = $this->createAuthenticatedClient($alice);
        $client->request('DELETE', '/api/v1/files/123e4567-e89b-12d3-a456-426614174000');

        static::assertResponseStatusCodeSame(404);
    }
}
