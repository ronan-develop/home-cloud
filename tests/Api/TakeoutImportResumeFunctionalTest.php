<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\AuthenticatedApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * TDD RED → GREEN (#481 — reprise après fermeture d'onglet en cours
 * d'upload) : si l'utilisateur ferme l'onglet en plein envoi d'un ZIP
 * Takeout, l'import reste "pending" côté serveur avec les chunks déjà
 * reçus. Ces deux endpoints permettent au front de retrouver cet import
 * et de savoir où reprendre chaque fichier, plutôt que de tout renvoyer
 * depuis 0 (constaté en conditions réelles : deux imports orphelins
 * nettoyés manuellement par SSH le 2026-09-27).
 */
final class TakeoutImportResumeFunctionalTest extends AuthenticatedApiTestCase
{
    private function makeChunkFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'hc_chunk_');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'chunk', 'application/octet-stream', null, true);
    }

    public function testFindPendingReturns404WhenUserHasNoPendingImport(): void
    {
        $user = $this->createUser('takeout-resume-none@example.com');
        $client = $this->createAuthenticatedClient($user);

        $client->request('GET', '/api/v1/takeout-imports/pending');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testFindPendingReturnsMostRecentPendingImportOwnedByUser(): void
    {
        $user = $this->createUser('takeout-resume-owner@example.com');
        $client = $this->createAuthenticatedClient($user);
        $client->disableReboot();

        $client->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);
        $importId = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('GET', '/api/v1/takeout-imports/pending');

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($importId, $data['id']);
        self::assertSame('pending', $data['status']);
    }

    public function testFindPendingNeverReturnsAnotherUsersImport(): void
    {
        $owner = $this->createUser('takeout-resume-owner2@example.com');
        $ownerClient = $this->createAuthenticatedClient($owner);
        $ownerClient->disableReboot();
        $ownerClient->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);

        $otherUser = $this->createUser('takeout-resume-other@example.com');
        $otherClient = $this->createAuthenticatedClient($otherUser);

        $otherClient->request('GET', '/api/v1/takeout-imports/pending');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testFilesStatusReturnsEmptyArrayWhenNoFileUploadedYet(): void
    {
        $user = $this->createUser('takeout-resume-empty@example.com');
        $client = $this->createAuthenticatedClient($user);
        $client->disableReboot();

        $client->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);
        $importId = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('GET', "/api/v1/takeout-imports/{$importId}/files/status");

        $this->assertResponseStatusCodeSame(200);
        self::assertSame([], json_decode($client->getResponse()->getContent(), true));
    }

    public function testFilesStatusReflectsPartiallyUploadedFile(): void
    {
        $user = $this->createUser('takeout-resume-partial@example.com');
        $client = $this->createAuthenticatedClient($user);
        $client->disableReboot();

        $client->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);
        $importId = json_decode($client->getResponse()->getContent(), true)['id'];

        $client->request('POST', "/api/v1/takeout-imports/{$importId}/files", [
            'extra' => [
                'parameters' => [
                    'filename' => 'takeout-001.zip',
                    'chunkIndex' => 0,
                    'totalChunks' => 3,
                ],
                'files' => ['file' => $this->makeChunkFile('AAA')],
            ],
        ]);
        $this->assertResponseStatusCodeSame(204);

        $client->request('GET', "/api/v1/takeout-imports/{$importId}/files/status");

        $this->assertResponseStatusCodeSame(200);
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertCount(1, $data);
        self::assertSame('takeout-001.zip', $data[0]['filename']);
        self::assertSame(0, $data[0]['chunkIndex']);
        self::assertSame(hash('sha256', 'AAA'), $data[0]['hashOfFirstChunk']);
    }

    public function testFilesStatusDeniesAccessToNonOwner(): void
    {
        $owner = $this->createUser('takeout-resume-status-owner@example.com');
        $ownerClient = $this->createAuthenticatedClient($owner);
        $ownerClient->disableReboot();
        $ownerClient->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);
        $importId = json_decode($ownerClient->getResponse()->getContent(), true)['id'];

        $otherUser = $this->createUser('takeout-resume-status-other@example.com');
        $otherClient = $this->createAuthenticatedClient($otherUser);

        $otherClient->request('GET', "/api/v1/takeout-imports/{$importId}/files/status");

        $this->assertResponseStatusCodeSame(403);
    }
}
