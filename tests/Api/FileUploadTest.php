<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Folder;
use App\Entity\User;
use App\Tests\AuthenticatedApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileUploadTest extends AuthenticatedApiTestCase
{
    private User $alice;
    private Folder $folder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = $this->createUser('alice@example.com', 'password123', 'Alice');
        $this->folder = $this->createFolder('TestFolder', $this->alice);
    }

    /**
     * Test d'upload multipart avec BrowserKit natif
     */
    public function testUploadFileWithMultipart(): void
    {
        $client = $this->createAuthenticatedKernelBrowser($this->alice);
        $tempFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        file_put_contents($tempFile, str_repeat('A', 10 * 1024 * 1024)); // 10 Mo

        $uploadedFile = new UploadedFile(
            $tempFile,
            'bigfile.txt',
            'text/plain',
            null,
            false
        );

        $client->request(
            'POST',
            '/api/v1/files',
            [
                'ownerId' => (string) $this->alice->getId(),
                'folderId' => (string) $this->folder->getId(),
            ],
            [
                'file' => $uploadedFile,
            ],
            [
                'CONTENT_TYPE' => 'multipart/form-data',
            ]
        );

        $this->assertResponseStatusCodeSame(201);
        @unlink($tempFile);
    }

    /**
     * #238 — glisser-déposer un dossier local : le fichier est reçu avec
     * relativePath, la sous-arborescence doit être recréée sous le folder cible.
     */
    public function testUploadFileWithRelativePathCreatesNestedFolder(): void
    {
        $client = $this->createAuthenticatedKernelBrowser($this->alice);
        $tempFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        file_put_contents($tempFile, 'contenu du scan');

        $uploadedFile = new UploadedFile($tempFile, 'scan1.pdf', 'application/pdf', null, false);

        $client->request(
            'POST',
            '/api/v1/files',
            [
                'ownerId' => (string) $this->alice->getId(),
                'folderId' => (string) $this->folder->getId(),
                'relativePath' => '2026-07-10-BMA',
            ],
            [
                'file' => $uploadedFile,
            ],
            [
                'CONTENT_TYPE' => 'multipart/form-data',
            ]
        );

        $this->assertResponseStatusCodeSame(201);
        @unlink($tempFile);

        $em = static::getContainer()->get('doctrine')->getManager();
        $subFolder = $em->getRepository(Folder::class)->findOneBy([
            'name' => '2026-07-10-BMA',
            'owner' => $this->alice,
        ]);
        $this->assertNotNull($subFolder, 'Le sous-dossier doit avoir été créé');
        $this->assertSame((string) $this->folder->getId(), (string) $subFolder->getParent()->getId());

        $createdFile = $em->getRepository(\App\Entity\File::class)->findOneBy(['originalName' => 'scan1.pdf']);
        $this->assertNotNull($createdFile);
        $this->assertSame((string) $subFolder->getId(), (string) $createdFile->getFolder()->getId());
    }

    /**
     * #441 — IDOR : ownerId ne doit jamais venir du client. Bob s'authentifie
     * mais tente d'uploader en usurpant l'identité d'Alice via le champ
     * ownerId du formulaire ; le fichier créé doit appartenir à Bob (l'utilisateur
     * authentifié), jamais à Alice.
     */
    public function testUploadIgnoresClientSuppliedOwnerIdAndUsesAuthenticatedUser(): void
    {
        $bob = $this->createUser('bob@example.com', 'password123', 'Bob');
        $client = $this->createAuthenticatedKernelBrowser($bob);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        file_put_contents($tempFile, 'contenu tentative usurpation');
        $uploadedFile = new UploadedFile($tempFile, 'spoof.txt', 'text/plain', null, false);

        $client->request(
            'POST',
            '/api/v1/files',
            [
                'ownerId' => (string) $this->alice->getId(),
            ],
            [
                'file' => $uploadedFile,
            ],
            [
                'CONTENT_TYPE' => 'multipart/form-data',
            ]
        );

        $this->assertResponseStatusCodeSame(201);
        @unlink($tempFile);

        $em = static::getContainer()->get('doctrine')->getManager();
        $createdFile = $em->getRepository(\App\Entity\File::class)->findOneBy(['originalName' => 'spoof.txt']);
        $this->assertNotNull($createdFile);
        $this->assertSame(
            (string) $bob->getId(),
            (string) $createdFile->getOwner()->getId(),
            'Le fichier doit appartenir à Bob (utilisateur authentifié), pas à Alice (ownerId usurpé dans le body)'
        );
    }
}
