<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Entity\Media;
use App\Entity\Folder;
use App\Entity\UploadBatch;
use App\Entity\User;
use App\Tests\AuthenticatedApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * TDD RED — #339 : flag `processSync` sur POST /api/v1/files.
 *
 * Permet à un appelant (import direct dans un album) d'obtenir le
 * `mediaId` du Media créé dans la réponse HTTP immédiate, en forçant le
 * même traitement synchrone que celui déjà utilisé par AlbumImportService
 * — sans emprunter le worker Messenger ni kernel.terminate (routage
 * exclusif à trois branches, cf. commentaire de routage dans
 * FileUploadController).
 */
final class FileUploadProcessSyncTest extends AuthenticatedApiTestCase
{
    private User $alice;
    private Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = $this->createUser('alice-sync@example.com', 'password123', 'Alice');
        $this->folder = $this->createFolder('TestFolder', $this->alice);
    }

    public function testProcessSyncReturnsMediaIdImmediatelyWithoutDispatchingToWorker(): void
    {
        $client = $this->createAuthenticatedKernelBrowser($this->alice);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_sync_');
        $image = imagecreatetruecolor(100, 80);
        imagejpeg($image, $tempFile);
        imagedestroy($image);

        $client->request(
            'POST',
            '/api/v1/files',
            [
                'ownerId'     => (string) $this->alice->getId(),
                'folderId'    => (string) $this->folder->getId(),
                'processSync' => '1',
            ],
            ['file' => new UploadedFile($tempFile, 'sync.jpg', 'image/jpeg', null, false)],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(201);
        @unlink($tempFile);

        $response = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('mediaId', $response);
        $this->assertNotNull($response['mediaId'], 'mediaId doit être présent dans la réponse en mode processSync');

        $media = $this->em->getRepository(Media::class)->find(Uuid::fromString($response['mediaId']));
        $this->assertNotNull($media, 'Le Media référencé par mediaId doit exister en base');
        $this->assertNotNull($media->getThumbnailPath(), 'La vignette doit déjà être générée');

        $transport = static::getContainer()->get('messenger.transport.async');
        $this->assertCount(0, $transport->get(), 'processSync ne doit jamais dispatcher au worker');
    }

    public function testProcessSyncOnNonMediaFileReturnsNullMediaIdWithoutError(): void
    {
        $client = $this->createAuthenticatedKernelBrowser($this->alice);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_sync_pdf_');
        file_put_contents($tempFile, '%PDF-1.4 fake content');

        $client->request(
            'POST',
            '/api/v1/files',
            [
                'ownerId'     => (string) $this->alice->getId(),
                'folderId'    => (string) $this->folder->getId(),
                'processSync' => '1',
            ],
            ['file' => new UploadedFile($tempFile, 'doc.pdf', 'application/pdf', null, false)],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(201);
        @unlink($tempFile);

        $response = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('mediaId', $response);
        $this->assertNull($response['mediaId']);
    }

    public function testWithoutProcessSyncMediaIdIsNullInImmediateResponse(): void
    {
        $client = $this->createAuthenticatedKernelBrowser($this->alice);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_nosync_');
        $image = imagecreatetruecolor(100, 80);
        imagejpeg($image, $tempFile);
        imagedestroy($image);

        $client->request(
            'POST',
            '/api/v1/files',
            [
                'ownerId'  => (string) $this->alice->getId(),
                'folderId' => (string) $this->folder->getId(),
            ],
            ['file' => new UploadedFile($tempFile, 'nosync.jpg', 'image/jpeg', null, false)],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(201);
        @unlink($tempFile);

        $response = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('mediaId', $response);
        $this->assertNull(
            $response['mediaId'],
            'Sans processSync, le Media n\'existe pas encore au moment de la réponse (kernel.terminate)'
        );
    }

    /**
     * Garde-fou du routage à trois branches exclusives : processSync ne doit
     * jamais se combiner avec un batchId deferred — un fichier ne doit
     * jamais être éligible à deux chemins de traitement en même temps.
     */
    public function testProcessSyncCombinedWithDeferredBatchIsRejected(): void
    {
        $batch = new UploadBatch($this->alice, 1, 300_000_000, UploadBatch::MODE_DEFERRED);
        $this->em->persist($batch);
        $this->em->flush();

        $client = $this->createAuthenticatedKernelBrowser($this->alice);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_sync_conflict_');
        $image = imagecreatetruecolor(100, 80);
        imagejpeg($image, $tempFile);
        imagedestroy($image);

        $client->request(
            'POST',
            '/api/v1/files',
            [
                'ownerId'     => (string) $this->alice->getId(),
                'folderId'    => (string) $this->folder->getId(),
                'processSync' => '1',
                'batchId'     => (string) $batch->getId(),
            ],
            ['file' => new UploadedFile($tempFile, 'conflict.jpg', 'image/jpeg', null, false)],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        @unlink($tempFile);

        $this->assertResponseStatusCodeSame(400);
    }
}
