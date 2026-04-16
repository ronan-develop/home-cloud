<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Entity\File;
use App\Tests\AuthenticatedApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class FileTest extends AuthenticatedApiTestCase
{
    protected EntityManagerInterface $em;

    // ...existing code...

    public function testPostFileCreatesFileAndReturns201(): void
    {
        $uniqueEmail = 'filetest_' . uniqid() . '@example.com';
        $user = $this->createUser($uniqueEmail, 'password123', 'FileTestUser');
        $folder = $this->createFolder('Docs_' . uniqid(), $user, null, $this->em);
        $uniqueName = 'file_' . Uuid::v4()->toRfc4122() . '.txt';
        $filePath = '/tmp/' . $uniqueName;
        file_put_contents($filePath, 'Dummy content');

        $browser = $this->createAuthenticatedClient($user)->getKernelBrowser();
        $uploadedFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
            $filePath,
            $uniqueName,
            'text/plain',
            null,
            true
        );

        $browser->request('POST', '/api/v1/files', [
            'ownerId' => (string) $user->getId(),
            'folderId' => (string) $folder->getId(),
        ], [
            'file' => $uploadedFile,
        ], [
            'CONTENT_TYPE' => 'multipart/form-data',
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($browser->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame($uniqueName, $data['originalName']);
        @unlink($filePath);
    }

    public function testGetFileReturns200WithCorrectData(): void
    {
        $uniqueEmail = 'fileget_' . uniqid() . '@example.com';
        $user   = $this->createUser($uniqueEmail, 'password123', 'FileGetUser');
        $folder = $this->createFolder('Docs_' . uniqid(), $user, null, $this->em);
        $unique = uniqid('file_', true);
        $file   = new File(
            "test_get_{$unique}.txt",
            'text/plain',
            42,
            "2026/02/test_get_{$unique}.txt",
            $folder,
            $user,
            false
        );
        $this->em->persist($file);
        $this->em->flush();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/v1/files/' . $file->getId());

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertStringStartsWith('test_get_', $data['originalName']);
        $this->assertSame((string) $folder->getId(), $data['folderId']);
        $this->assertSame('/api/v1/files/' . $file->getId(), $data['@id']);
    }

    public function testGetFileReturns404WhenNotFound(): void
    {
        $client = static::createClient();
        \App\Tests\Api\ApiTestHelper::withFakeJwt($client);
        $uuid = '123e4567-e89b-12d3-a456-426614174000';
        $client->request('GET', "/api/v1/files/$uuid");
        $this->assertResponseStatusCodeSame(404);
    }

    public function testPostFileReturns400WhenMissingFields(): void
    {
        $client = static::createClient();
        \App\Tests\Api\ApiTestHelper::withFakeJwt($client);
        $client->request('POST', '/api/v1/files', [
            'json' => [
                // missing required fields
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }
}
