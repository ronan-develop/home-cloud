<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\AuthenticatedApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * TDD RED → GREEN : liste de tous les imports Takeout "pending" d'un
 * utilisateur (pas seulement le plus récent, cf. #491) avec reprise/abandon
 * explicites — cas rare mais réel de plusieurs imports pending simultanés
 * (deux tentatives depuis deux appareils/navigateurs différents).
 */
final class TakeoutImportPendingListFunctionalTest extends AuthenticatedApiTestCase
{
    private function makeChunkFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'hc_chunk_');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'chunk', 'application/octet-stream', null, true);
    }

    public function testReturnsEmptyArrayWhenNoPendingImport(): void
    {
        $user = $this->createUser('takeout-list-none@example.com');
        $client = $this->createAuthenticatedClient($user);

        $client->request('GET', '/api/v1/takeout-imports/pending-list');

        $this->assertResponseStatusCodeSame(200);
        self::assertSame([], json_decode($client->getResponse()->getContent(), true));
    }

    public function testListsAllPendingImportsOwnedByUser(): void
    {
        $user = $this->createUser('takeout-list-multi@example.com');
        $client = $this->createAuthenticatedClient($user);
        $client->disableReboot();

        $client->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);
        $importId1 = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);
        $importId2 = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('GET', '/api/v1/takeout-imports/pending-list');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(2, $data);
        $ids = array_column($data, 'id');
        self::assertContains($importId1, $ids);
        self::assertContains($importId2, $ids);
    }

    public function testNeverListsAnotherUsersImport(): void
    {
        $owner = $this->createUser('takeout-list-owner@example.com');
        $ownerClient = $this->createAuthenticatedClient($owner);
        $ownerClient->disableReboot();
        $ownerClient->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);

        $otherUser = $this->createUser('takeout-list-other@example.com');
        $otherClient = $this->createAuthenticatedClient($otherUser);

        $otherClient->request('GET', '/api/v1/takeout-imports/pending-list');

        $this->assertResponseStatusCodeSame(200);
        self::assertSame([], json_decode($otherClient->getResponse()->getContent(), true));
    }

    public function testIncludesFilesUploadedCountPerImport(): void
    {
        $user = $this->createUser('takeout-list-count@example.com');
        $client = $this->createAuthenticatedClient($user);
        $client->disableReboot();

        $client->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);
        $importId = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('POST', "/api/v1/takeout-imports/{$importId}/files", [
            'extra' => [
                'parameters' => ['filename' => 'takeout-001.zip', 'chunkIndex' => 0, 'totalChunks' => 1],
                'files' => ['file' => $this->makeChunkFile('AAA')],
            ],
        ]);
        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/v1/takeout-imports/pending-list');

        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(1, $data[0]['filesUploadedCount']);
    }

    public function testAbandonDeletesThePendingImport(): void
    {
        $user = $this->createUser('takeout-abandon@example.com');
        $client = $this->createAuthenticatedClient($user);
        $client->disableReboot();

        $client->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);
        $importId = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('DELETE', "/api/v1/takeout-imports/{$importId}");
        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/v1/takeout-imports/pending-list');
        self::assertSame([], json_decode($client->getResponse()->getContent(), true));
    }

    public function testAbandonRemovesUploadedFilesFromDisk(): void
    {
        $user = $this->createUser('takeout-abandon-files@example.com');
        $client = $this->createAuthenticatedClient($user);
        $client->disableReboot();

        $client->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);
        $importId = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('POST', "/api/v1/takeout-imports/{$importId}/files", [
            'extra' => [
                'parameters' => ['filename' => 'takeout-001.zip', 'chunkIndex' => 0, 'totalChunks' => 1],
                'files' => ['file' => $this->makeChunkFile('AAA')],
            ],
        ]);
        $this->assertResponseStatusCodeSame(204);

        $importTmpDir = sprintf('%s/var/takeout-tmp/%s', dirname(__DIR__, 2), $importId);
        self::assertDirectoryExists($importTmpDir);

        $client->request('DELETE', "/api/v1/takeout-imports/{$importId}");
        $this->assertResponseStatusCodeSame(204);

        self::assertDirectoryDoesNotExist($importTmpDir);
    }

    public function testAbandonDeniesAccessToNonOwner(): void
    {
        $owner = $this->createUser('takeout-abandon-owner@example.com');
        $ownerClient = $this->createAuthenticatedClient($owner);
        $ownerClient->disableReboot();
        $ownerClient->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);
        $importId = json_decode($ownerClient->getResponse()->getContent(), true)['id'];

        $otherUser = $this->createUser('takeout-abandon-other@example.com');
        $otherClient = $this->createAuthenticatedClient($otherUser);

        $otherClient->request('DELETE', "/api/v1/takeout-imports/{$importId}");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAbandonReturns404ForUnknownImport(): void
    {
        $user = $this->createUser('takeout-abandon-unknown@example.com');
        $client = $this->createAuthenticatedClient($user);

        $client->request('DELETE', '/api/v1/takeout-imports/01a0e000-0000-7000-8000-000000000000');

        $this->assertResponseStatusCodeSame(404);
    }
}
