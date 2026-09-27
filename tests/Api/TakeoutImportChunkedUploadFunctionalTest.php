<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\TakeoutImport;
use App\Tests\AuthenticatedApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * TDD RED → GREEN (#466) : un ZIP Google Photos Takeout peut dépasser 512M
 * (plafond dur de l'hébergement mutualisé o2switch, non contournable via
 * .user.ini — confirmé en conditions réelles avec des archives ~2GB). Le
 * front découpe chaque fichier en tranches envoyées séquentiellement à
 * POST /v1/takeout-imports/{id}/files ; ce test vérifie le réassemblage
 * serveur de bout en bout, indépendamment de TakeoutImportFunctionalTest
 * qui couvre le cas non découpé (totalChunks=1, valeur par défaut).
 */
final class TakeoutImportChunkedUploadFunctionalTest extends AuthenticatedApiTestCase
{
    private function makeChunkFile(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'hc_chunk_');
        file_put_contents($path, $content);

        return new UploadedFile($path, 'chunk', 'application/octet-stream', null, true);
    }

    public function testReassemblesFileFromMultipleChunks(): void
    {
        $user = $this->createUser('takeout-chunk@example.com');
        $client = $this->createAuthenticatedClient($user);
        $client->disableReboot();

        $client->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201, (string) $client->getResponse()->getContent());
        $importId = json_decode($client->getResponse()->getContent(), true)['id'];

        $chunks = ['AAA', 'BBB', 'CCC'];
        foreach ($chunks as $index => $chunkContent) {
            $client->request('POST', "/api/v1/takeout-imports/{$importId}/files", [
                'extra' => [
                    'parameters' => [
                        'filename' => 'takeout-001.zip',
                        'chunkIndex' => $index,
                        'totalChunks' => count($chunks),
                    ],
                    'files' => ['file' => $this->makeChunkFile($chunkContent)],
                ],
            ]);
            $this->assertResponseStatusCodeSame(204, (string) $client->getResponse()->getContent());
        }

        $client->request('POST', "/api/v1/takeout-imports/{$importId}/start");
        $this->assertResponseStatusCodeSame(202, (string) $client->getResponse()->getContent());
    }

    public function testRejectsChunkArrivingOutOfOrderWith400(): void
    {
        $user = $this->createUser('takeout-chunk-order@example.com');
        $client = $this->createAuthenticatedClient($user);
        $client->disableReboot();

        $client->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);
        $importId = json_decode($client->getResponse()->getContent(), true)['id'];

        // Chunk 1 envoyé directement, sans le chunk 0 préalable.
        $client->request('POST', "/api/v1/takeout-imports/{$importId}/files", [
            'extra' => [
                'parameters' => [
                    'filename' => 'takeout-001.zip',
                    'chunkIndex' => 1,
                    'totalChunks' => 3,
                ],
                'files' => ['file' => $this->makeChunkFile('BBB')],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRetryingSameChunkDoesNotDuplicateContent(): void
    {
        $user = $this->createUser('takeout-chunk-retry@example.com');
        $client = $this->createAuthenticatedClient($user);
        $client->disableReboot();

        $client->request('POST', '/api/v1/takeout-imports');
        $this->assertResponseStatusCodeSame(201);
        $importId = json_decode($client->getResponse()->getContent(), true)['id'];

        $sendChunk = function (int $index, string $content) use ($client, $importId) {
            $client->request('POST', "/api/v1/takeout-imports/{$importId}/files", [
                'extra' => [
                    'parameters' => [
                        'filename' => 'takeout-001.zip',
                        'chunkIndex' => $index,
                        'totalChunks' => 2,
                    ],
                    'files' => ['file' => $this->makeChunkFile($content)],
                ],
            ]);
            $this->assertResponseStatusCodeSame(204, (string) $client->getResponse()->getContent());
        };

        $sendChunk(0, 'AAA');
        $sendChunk(0, 'AAA'); // retry réseau
        $sendChunk(1, 'BBB');

        $client->request('POST', "/api/v1/takeout-imports/{$importId}/start");
        $this->assertResponseStatusCodeSame(202);

        $import = $this->em->getRepository(TakeoutImport::class)->find($importId);
        self::assertNotNull($import);
    }
}
