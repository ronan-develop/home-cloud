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
        $client = $this->createAuthenticatedClient($this->alice)->getKernelBrowser();
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
}
