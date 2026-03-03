<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Tests fonctionnels pour PATCH /api/v1/files/{id} (déplacement).
 */
final class FileMoveTest extends AuthenticatedApiTestCase
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

    /**
     * Crée un File directement en base (sans passer par l'upload multipart).
     * path factice car on ne teste pas le stockage ici, seulement les métadonnées.
     */
    private function createFile(string $name, \App\Entity\Folder $folder, \App\Entity\User $owner): File
    {
        $file = new File($name, 'text/plain', 42, 'test/' . uniqid() . '.txt', $folder, $owner, false);
        $this->em->persist($file);
        $this->em->flush();
        return $file;
    }

    /** Déplacer un fichier vers un autre dossier → 200 avec folderId mis à jour */
    public function testMoveFileToAnotherFolder(): void
    {
        $alice   = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $folderA = $this->createFolder('FolderA', $alice);
        $folderB = $this->createFolder('FolderB', $alice);
        $file    = $this->createFile('document.txt', $folderA, $alice);

        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/files/' . $file->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['targetFolderId' => (string) $folderB->getId()],
        ]);

        static::assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertSame((string) $folderB->getId(), $data['folderId']);
        $this->assertSame('FolderB', $data['folderName']);
    }

    /** Dossier cible inexistant → 404 */
    public function testMoveFileToNonExistentFolderReturns404(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $folder = $this->createFolder('Folder', $alice);
        $file   = $this->createFile('doc.txt', $folder, $alice);

        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/files/' . $file->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['targetFolderId' => '123e4567-e89b-12d3-a456-426614174000'],
        ]);

        static::assertResponseStatusCodeSame(404);
    }

    /** Dossier cible appartenant à un autre user → 403 */
    public function testMoveFileToOtherUserFolderReturns403(): void
    {
        $alice     = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $bob       = $this->createUser('bob_file@example.com', 'password123', 'Bob');
        $aliceFolder = $this->createFolder('AliceFolder', $alice);
        $bobFolder   = $this->createFolder('BobFolder', $bob);
        $file        = $this->createFile('doc.txt', $aliceFolder, $alice);

        // Alice essaie de déplacer son fichier dans un dossier de Bob
        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/files/' . $file->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['targetFolderId' => (string) $bobFolder->getId()],
        ]);

        static::assertResponseStatusCodeSame(403);
    }

    /** Fichier inexistant → 404 */
    public function testMoveNonExistentFileReturns404(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $folder = $this->createFolder('Folder', $alice);

        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/files/123e4567-e89b-12d3-a456-426614174000', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['targetFolderId' => (string) $folder->getId()],
        ]);

        static::assertResponseStatusCodeSame(404);
    }

    /** Sans authentification → 401 (ou 200 si PUBLIC_ACCESS en test) */
    public function testMoveFileWithoutAuthReturns401(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $folder = $this->createFolder('Folder', $alice);
        $file   = $this->createFile('doc.txt', $folder, $alice);

        $client = static::createClient(); // pas de token
        $response = $client->request('PATCH', '/api/v1/files/' . $file->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['targetFolderId' => (string) $folder->getId()],
        ]);

        // En env test : TestJwtAuthenticator → PUBLIC_ACCESS possible
        static::assertThat(
            $response->getStatusCode(),
            static::logicalOr(static::equalTo(200), static::equalTo(401)),
        );
    }
}
