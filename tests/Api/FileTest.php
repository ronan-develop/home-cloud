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
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    public function testPostFileCreatesFileAndReturns201(): void
    {
        $client = static::createClient();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $this->testUserEmail]);
        $folder = $this->createFolder('Docs', $user, null, $this->em);
        $uniqueName = 'file_' . Uuid::v4()->toRfc4122() . '.txt';
        $filePath = '/tmp/' . $uniqueName;
        file_put_contents($filePath, 'Dummy content');

        $client->request('POST', '/api/v1/files', [
            'headers' => [
                'Content-Type' => 'multipart/form-data',
            ],
            'multipart' => [
                [
                    'name'     => 'file',
                    'contents' => fopen($filePath, 'r'),
                    'filename' => $uniqueName,
                ],
                [
                    'name'     => 'name',
                    'contents' => $uniqueName,
                ],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertSame($uniqueName, $data['name']);
        $this->assertSame('/api/v1/folders/' . $folder->getId(), $data['folder']);
        @unlink($filePath);
    }

    public function testGetFileReturns200WithCorrectData(): void
    {
        $this->markTestSkipped('Temporary: Entity isolation issue with UUID generation');
        $client = static::createClient();
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $this->testUserEmail]);
        $folder = $this->createFolder('Docs', $user, null, $this->em);
        $unique = uniqid('file_', true);
        $file = new File(
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
        $client->request('GET', '/api/v1/files/' . $file->getId());
        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringStartsWith('test_get_', $data['name']);
        $this->assertSame('/api/v1/folders/' . $folder->getId(), $data['folder']);
        $this->assertSame('/api/v1/files/' . $file->getId(), $data['@id']);
    }

    public function testGetFileReturns404WhenNotFound(): void
    {
        $client = static::createClient();
        $uuid = '123e4567-e89b-12d3-a456-426614174000';
        $client->request('GET', "/api/v1/files/$uuid");
        $this->assertResponseStatusCodeSame(404);
    }

    public function testPostFileReturns400WhenMissingFields(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/v1/files', [
            'json' => [
                // missing required fields
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
    }
}
