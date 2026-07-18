<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
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
}
