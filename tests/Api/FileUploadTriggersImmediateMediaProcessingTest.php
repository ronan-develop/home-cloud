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
}
