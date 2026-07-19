<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\UploadBatch;
use App\Entity\User;
use App\Tests\AuthenticatedApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * L'upload dispatchait MediaProcessMessage sur le transport async : sans
 * worker qui dépile (ou en attendant le prochain cycle de cron), la vignette
 * n'apparaissait jamais avant plusieurs minutes — inutilisable pour un
 * multi-upload réel (cf. #251).
 *
 * Le traitement est désormais aussi déclenché immédiatement après la réponse
 * HTTP (kernel.terminate), sans attendre le worker : le Media doit exister
 * dès la fin de la requête de test, sans consommer explicitement la file.
 */
final class FileUploadTriggersImmediateMediaProcessingTest extends AuthenticatedApiTestCase
{
    private User $alice;
    private Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = $this->createUser('alice-immediate@example.com', 'password123', 'Alice');
        $this->folder = $this->createFolder('TestFolder', $this->alice);
    }

    public function testMediaExistsImmediatelyAfterUploadWithoutConsumingTheQueue(): void
    {
        $client = $this->createAuthenticatedKernelBrowser($this->alice);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        $image = imagecreatetruecolor(200, 150);
        imagejpeg($image, $tempFile);
        imagedestroy($image);

        $uploadedFile = new UploadedFile($tempFile, 'photo.jpg', 'image/jpeg', null, false);

        $client->request(
            'POST',
            '/api/v1/files',
            [
                'ownerId'  => (string) $this->alice->getId(),
                'folderId' => (string) $this->folder->getId(),
            ],
            ['file' => $uploadedFile],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(201);
        @unlink($tempFile);

        $response = json_decode((string) $client->getResponse()->getContent(), true);
        $fileId = (string) $response['id'];

        $file = $this->em->getRepository(File::class)->find(Uuid::fromString($fileId));
        $this->assertNotNull($file);

        $media = $this->em->getRepository(Media::class)->findOneBy(['file' => $file]);

        $this->assertNotNull(
            $media,
            'Le Media doit exister immédiatement après la requête, sans consommer la file Messenger'
        );
        $this->assertNotNull($media->getThumbnailPath(), 'La vignette doit être générée dans la foulée');
    }

    /**
     * Les navigateurs n'ont pas de mimeType pour les RAW et envoient
     * "application/octet-stream" (cf. commentaire de MediaProcessor sur
     * RAW_EXTENSIONS). Si le dispatch se limite à `image/*`/`video/*`, un
     * upload RAW via l'API ne crée jamais de Media — la reconnaissance par
     * extension de MediaProcessor ne sert à rien si le message ne part
     * jamais.
     */
    public function testRawUploadWithOctetStreamMimeTypeStillCreatesMedia(): void
    {
        $client = $this->createAuthenticatedKernelBrowser($this->alice);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_raw_');
        file_put_contents($tempFile, 'not-a-real-raw-file-content');

        $uploadedFile = new UploadedFile($tempFile, 'photo.nef', 'application/octet-stream', null, false);

        $client->request(
            'POST',
            '/api/v1/files',
            [
                'ownerId'  => (string) $this->alice->getId(),
                'folderId' => (string) $this->folder->getId(),
            ],
            ['file' => $uploadedFile],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(201);
        @unlink($tempFile);

        $response = json_decode((string) $client->getResponse()->getContent(), true);
        $fileId = (string) $response['id'];

        $file = $this->em->getRepository(File::class)->find(Uuid::fromString($fileId));
        $this->assertNotNull($file);

        $media = $this->em->getRepository(Media::class)->findOneBy(['file' => $file]);

        $this->assertNotNull(
            $media,
            'Un RAW envoyé en application/octet-stream doit quand même produire un Media (reconnu par extension)'
        );
        $this->assertSame('photo', $media->getMediaType());
    }

    /**
     * Symétrique du test PDF côté dispatch (MediaTest::testUploadNonImageDoesNotDispatchMediaMessage) :
     * ferme la boucle côté traitement immédiat (kernel.terminate) introduit pour #251.
     */
    public function testPdfUploadNeverCreatesMedia(): void
    {
        $client = $this->createAuthenticatedKernelBrowser($this->alice);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_pdf_');
        file_put_contents($tempFile, '%PDF-1.4 fake content');

        $uploadedFile = new UploadedFile($tempFile, 'doc.pdf', 'application/pdf', null, false);

        $client->request(
            'POST',
            '/api/v1/files',
            [
                'ownerId'  => (string) $this->alice->getId(),
                'folderId' => (string) $this->folder->getId(),
            ],
            ['file' => $uploadedFile],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(201);
        @unlink($tempFile);

        $response = json_decode((string) $client->getResponse()->getContent(), true);
        $fileId = (string) $response['id'];

        $file = $this->em->getRepository(File::class)->find(Uuid::fromString($fileId));
        $this->assertNotNull($file);

        $media = $this->em->getRepository(Media::class)->findOneBy(['file' => $file]);

        $this->assertNull($media, 'Un PDF ne doit jamais produire de Media');
    }

    /**
     * Routage exclusif — lot "deferred" (lourd) : le fichier part au worker
     * Messenger (1 message) et n'est PAS traité immédiatement (kernel.terminate).
     * L'utilisateur n'attend pas ; la vignette sera générée au cycle du worker.
     */
    public function testDeferredBatchDispatchesToWorkerAndSkipsImmediateProcessing(): void
    {
        $batch = new UploadBatch($this->alice, 1, 300_000_000, UploadBatch::MODE_DEFERRED);
        $this->em->persist($batch);
        $this->em->flush();
        $batchId = (string) $batch->getId();

        $client = $this->createAuthenticatedKernelBrowser($this->alice);

        $fileId = $this->uploadImage($client, 'deferred.jpg', $batchId);

        $transport = static::getContainer()->get('messenger.transport.async');
        $this->assertCount(1, $transport->get(), 'Un lot deferred doit dispatcher exactement un message worker');

        $file = $this->em->getRepository(File::class)->find(Uuid::fromString($fileId));
        $media = $this->em->getRepository(Media::class)->findOneBy(['file' => $file]);
        $this->assertNull(
            $media,
            'Un lot deferred ne doit PAS être traité immédiatement (kernel.terminate) — le worker s\'en charge'
        );
    }

    /**
     * Routage exclusif — lot "immediate" (petit) : traitement juste après la
     * réponse HTTP, AUCUN message worker (fin du no-op systématique du worker).
     */
    public function testImmediateBatchProcessesWithoutDispatchingToWorker(): void
    {
        $batch = new UploadBatch($this->alice, 1, 500_000, UploadBatch::MODE_IMMEDIATE);
        $this->em->persist($batch);
        $this->em->flush();
        $batchId = (string) $batch->getId();

        $client = $this->createAuthenticatedKernelBrowser($this->alice);

        $fileId = $this->uploadImage($client, 'immediate.jpg', $batchId);

        $transport = static::getContainer()->get('messenger.transport.async');
        $this->assertCount(0, $transport->get(), 'Un lot immediate ne doit jamais dispatcher au worker');

        $file = $this->em->getRepository(File::class)->find(Uuid::fromString($fileId));
        $media = $this->em->getRepository(Media::class)->findOneBy(['file' => $file]);
        $this->assertNotNull($media, 'Un lot immediate doit être traité dans la foulée de la réponse HTTP');
    }

    /**
     * Régression : un upload sans batchId (client ancien, upload isolé) reste
     * traité immédiatement et ne dispatche jamais au worker — fin du double
     * dispatch qui refaisait le travail en no-op.
     */
    public function testUploadWithoutBatchDoesNotDispatchToWorker(): void
    {
        $client = $this->createAuthenticatedKernelBrowser($this->alice);

        $fileId = $this->uploadImage($client, 'nobatch.jpg', null);

        $transport = static::getContainer()->get('messenger.transport.async');
        $this->assertCount(0, $transport->get(), 'Sans lot, aucun dispatch worker (traitement immédiat seul)');

        $file = $this->em->getRepository(File::class)->find(Uuid::fromString($fileId));
        $media = $this->em->getRepository(Media::class)->findOneBy(['file' => $file]);
        $this->assertNotNull($media, 'Sans lot, le Media doit exister immédiatement');
    }

    /**
     * Upload d'une vraie image JPEG via multipart, avec batchId optionnel.
     * Retourne l'id du File créé.
     */
    private function uploadImage(object $client, string $name, ?string $batchId): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        $image = imagecreatetruecolor(120, 90);
        imagejpeg($image, $tempFile);
        imagedestroy($image);

        $params = [
            'ownerId'  => (string) $this->alice->getId(),
            'folderId' => (string) $this->folder->getId(),
        ];
        if ($batchId !== null) {
            $params['batchId'] = $batchId;
        }

        $client->request(
            'POST',
            '/api/v1/files',
            $params,
            ['file' => new UploadedFile($tempFile, $name, 'image/jpeg', null, false)],
            ['CONTENT_TYPE' => 'multipart/form-data'],
        );

        $this->assertResponseStatusCodeSame(201);
        @unlink($tempFile);

        return (string) json_decode((string) $client->getResponse()->getContent(), true)['id'];
    }
}
