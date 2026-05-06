<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Validation d'unicité du nom de fichier lors d'un renommage ou d'un déplacement.
 */
final class FileUniquenessTest extends AuthenticatedApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createUser('alice@example.com', 'password123', 'Alice');
    }

    private function createFile(string $name, \App\Entity\Folder $folder, \App\Entity\User $owner): File
    {
        $file = new File($name, 'text/plain', 42, 'test/' . uniqid() . '.txt', $folder, $owner, false);
        $this->em->persist($file);
        $this->em->flush();
        return $file;
    }

    /** PATCH rename — nom déjà utilisé dans le même dossier → 400 */
    public function testRenameFileToExistingNameInSameFolderReturns400(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $folder = $this->createFolder('Docs', $alice);
        $this->createFile('existing.txt', $folder, $alice);
        $file   = $this->createFile('other.txt', $folder, $alice);

        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/files/' . $file->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['originalName' => 'existing.txt'],
        ]);

        static::assertResponseStatusCodeSame(400);
    }

    /** PATCH move — fichier de même nom déjà présent dans le dossier cible → 400 */
    public function testMoveFileToFolderWithExistingNameReturns400(): void
    {
        $alice   = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $folderA = $this->createFolder('FolderA', $alice);
        $folderB = $this->createFolder('FolderB', $alice);
        $this->createFile('conflict.txt', $folderB, $alice);
        $file    = $this->createFile('conflict.txt', $folderA, $alice);

        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/files/' . $file->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['targetFolderId' => (string) $folderB->getId()],
        ]);

        static::assertResponseStatusCodeSame(400);
    }

    /** PATCH rename — nom unique dans le dossier → 200 */
    public function testRenameFileToUniqueNameSucceeds(): void
    {
        $alice  = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $folder = $this->createFolder('Docs', $alice);
        $file   = $this->createFile('document.txt', $folder, $alice);

        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/files/' . $file->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['originalName' => 'renamed.txt'],
        ]);

        static::assertResponseStatusCodeSame(200);
        $this->assertSame('renamed.txt', $response->toArray()['originalName']);
    }

    /** PATCH move — pas de collision dans le dossier cible → 200 */
    public function testMoveFileNoCollisionSucceeds(): void
    {
        $alice   = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $folderA = $this->createFolder('FolderA', $alice);
        $folderB = $this->createFolder('FolderB', $alice);
        $file    = $this->createFile('unique.txt', $folderA, $alice);

        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('PATCH', '/api/v1/files/' . $file->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json'    => ['targetFolderId' => (string) $folderB->getId()],
        ]);

        static::assertResponseStatusCodeSame(200);
        $this->assertSame((string) $folderB->getId(), $response->toArray()['folderId']);
    }
}
