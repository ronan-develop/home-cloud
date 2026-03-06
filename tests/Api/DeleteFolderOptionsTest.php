<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Tests\AuthenticatedApiTestCase;

final class DeleteFolderOptionsTest extends AuthenticatedApiTestCase
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

    public function testDeleteFolderPreserveContentsMovesFilesToUploads(): void
    {
        $alice = $this->em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'alice@example.com']);
        $parent = $this->createFolder('Parent', $alice);
        $child = $this->createFolder('Child', $alice, $parent);
        $file1 = $this->createFile('one.txt', $parent, $alice);
        $file2 = $this->createFile('two.txt', $child, $alice);

        $client = $this->createAuthenticatedClient($alice);
        $response = $client->request('DELETE', '/api/v1/folders/' . $parent->getId(), [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['deleteContents' => false]
        ]);

        static::assertResponseStatusCodeSame(204);

        // Verify files moved to uploads
        $uploads = $this->em->getRepository(\App\Entity\Folder::class)
            ->findOneBy(['name' => 'Uploads', 'owner' => $alice]);
        static::assertNotNull($uploads, 'Uploads folder should exist');

        $f1 = $this->em->getRepository(\App\Entity\File::class)->find($file1->getId());
        $f2 = $this->em->getRepository(\App\Entity\File::class)->find($file2->getId());

        static::assertSame((string)$uploads->getId(), (string)$f1->getFolder()->getId());
        static::assertSame((string)$uploads->getId(), (string)$f2->getFolder()->getId());
    }
}
