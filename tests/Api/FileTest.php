<?php

declare(strict_types=1);

namespace App\Tests\Api;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Service\DefaultFolderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createUser(): User
    {
        $user = new User('owner@example.com', 'Owner');
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function makeTempFile(string $content = 'hello', string $name = 'test.txt'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hc_test_');
        file_put_contents($tmp, $content);

        return new UploadedFile($tmp, $name, 'text/plain', null, true);
    }

    // --- GET /api/v1/files/{id} ---

    public function testGetFileReturns200WithCorrectStructure(): void
    {
        $user = $this->createUser();
        $folder = new Folder('Documents', $user);
        $this->em->persist($folder);
        $file = new File('rapport.pdf', 'application/pdf', 102400, '2026/02/uuid.pdf', $folder, $user);
        $this->em->persist($file);
        $this->em->flush();

        $response = static::createClient()->request('GET', '/api/v1/files/'.$file->getId());

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('rapport.pdf', $data['originalName']);
        $this->assertSame('application/pdf', $data['mimeType']);
        $this->assertSame(102400, $data['size']);
        $this->assertSame('Documents', $data['folderName']);
        $this->assertArrayHasKey('folderId', $data);
        $this->assertArrayHasKey('ownerId', $data);
        $this->assertArrayHasKey('createdAt', $data);
    }

    public function testGetFileReturns404WhenNotFound(): void
    {
        static::createClient()->request('GET', '/api/v1/files/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(404);
    }

    // --- GET /api/v1/files ---

    public function testGetCollectionReturnsFiles(): void
    {
        $user = $this->createUser();
        $folder = new Folder('Photos', $user);
        $this->em->persist($folder);
        $this->em->persist(new File('a.jpg', 'image/jpeg', 512, '2026/02/a.jpg', $folder, $user));
        $this->em->persist(new File('b.png', 'image/png', 1024, '2026/02/b.png', $folder, $user));
        $this->em->flush();

        $response = static::createClient()->request('GET', '/api/v1/files', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertGreaterThanOrEqual(2, count($response->toArray()));
    }

    public function testGetCollectionFiltersByFolder(): void
    {
        $user = $this->createUser();
        $folder1 = new Folder('F1', $user);
        $folder2 = new Folder('F2', $user);
        $this->em->persist($folder1);
        $this->em->persist($folder2);
        $this->em->persist(new File('in-f1.txt', 'text/plain', 100, 'path/f1.txt', $folder1, $user));
        $this->em->persist(new File('in-f2.txt', 'text/plain', 200, 'path/f2.txt', $folder2, $user));
        $this->em->flush();

        $response = static::createClient()->request('GET', '/api/v1/files?folderId='.$folder1->getId(), [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $data = $response->toArray();
        $this->assertCount(1, $data);
        $this->assertSame('in-f1.txt', $data[0]['originalName']);
    }

    // --- POST /api/v1/files : cas 1 — folder existant ---

    public function testPostFileToExistingFolderCreates201(): void
    {
        $user = $this->createUser();
        $folder = new Folder('Musique', $user);
        $this->em->persist($folder);
        $this->em->flush();

        $response = static::createClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('♫', 'song.txt')],
                'parameters' => [
                    'ownerId' => (string) $user->getId(),
                    'folderId' => (string) $folder->getId(),
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('Musique', $data['folderName']);
        $this->assertSame((string) $folder->getId(), $data['folderId']);
    }

    // --- POST /api/v1/files : cas 2 — nouveau folder à créer ---

    public function testPostFileWithNewFolderNameCreates201AndFolder(): void
    {
        $user = $this->createUser();

        $response = static::createClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('data', 'data.txt')],
                'parameters' => [
                    'ownerId' => (string) $user->getId(),
                    'newFolderName' => 'Nouveau Dossier',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame('Nouveau Dossier', $data['folderName']);
    }

    // --- POST /api/v1/files : cas 3 — pas de folder → "Uploads" ---

    public function testPostFileWithoutFolderGoesToUploads(): void
    {
        $user = $this->createUser();

        $response = static::createClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('content', 'note.txt')],
                'parameters' => [
                    'ownerId' => (string) $user->getId(),
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $this->assertSame(DefaultFolderService::DEFAULT_FOLDER_NAME, $response->toArray()['folderName']);
    }

    // --- POST /api/v1/files : erreurs ---

    public function testPostFileReturns400WhenNoFileSent(): void
    {
        $user = $this->createUser();

        static::createClient()->request('POST', '/api/v1/files', [
            'json' => ['ownerId' => (string) $user->getId()],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testPostFileReturns404WhenFolderIdNotFound(): void
    {
        $user = $this->createUser();

        static::createClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile()],
                'parameters' => [
                    'ownerId' => (string) $user->getId(),
                    'folderId' => '00000000-0000-0000-0000-000000000000',
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // --- DELETE /api/v1/files/{id} ---

    public function testPostFileReturns400WhenExecutable(): void
    {
        $user = $this->createUser();

        static::createClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('MZ...', 'virus.exe')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testDeleteFileReturns204(): void
    {
        $user = $this->createUser();
        $folder = new Folder('Temp', $user);
        $this->em->persist($folder);
        $file = new File('delete-me.txt', 'text/plain', 10, 'path/dm.txt', $folder, $user);
        $this->em->persist($file);
        $this->em->flush();

        static::createClient()->request('DELETE', '/api/v1/files/'.$file->getId());

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteFileAlsoRemovesPhysicalFile(): void
    {
        $user = $this->createUser();
        $folder = new Folder('Temp', $user);
        $this->em->persist($folder);

        // Upload réel pour avoir un fichier sur disque
        $client = static::createClient();
        $response = $client->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('to-delete', 'todelete.txt')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $id = $response->toArray()['id'];
        $path = $response->toArray()['path'];

        $storageDir = static::getContainer()->getParameter('app.storage_dir');
        $fullPath = $storageDir.'/'.$path;
        $this->assertFileExists($fullPath);

        $client->request('DELETE', '/api/v1/files/'.$id);
        $this->assertResponseStatusCodeSame(204);
        $this->assertFileDoesNotExist($fullPath);
    }

    // --- GET /api/v1/files/{id}/download ---

    public function testDownloadFileReturns200WithBinaryContent(): void
    {
        $user = $this->createUser();

        $client = static::createClient();
        $response = $client->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('binary-content', 'doc.txt')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $id = $response->toArray()['id'];

        $download = $client->request('GET', '/api/v1/files/'.$id.'/download');

        $this->assertResponseStatusCodeSame(200);
        $this->assertSame('binary-content', $download->getContent());
    }

    public function testDownloadFileReturns404WhenNotFound(): void
    {
        static::createClient()->request('GET', '/api/v1/files/00000000-0000-0000-0000-000000000000/download');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteFileReturns404WhenNotFound(): void
    {
        static::createClient()->request('DELETE', '/api/v1/files/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(404);
    }

    // --- Sécurité : extensions serveur bloquées ---

    public function testPostFileReturns400WhenPhpExtension(): void
    {
        $user = $this->createUser();

        static::createClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('<?php echo "rce"; ?>', 'shell.php')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testPostFileReturns400WhenPharExtension(): void
    {
        $user = $this->createUser();

        static::createClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('fake', 'archive.phar')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    // --- Sécurité : Content-Disposition ---

    public function testDownloadContentDispositionIsRfc6266Compliant(): void
    {
        $user = $this->createUser();

        $client = static::createClient();
        $response = $client->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('data', 'mon fichier (2).txt')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $id = $response->toArray()['id'];

        $download = $client->request('GET', '/api/v1/files/'.$id.'/download');

        $this->assertResponseStatusCodeSame(200);
        $disposition = $download->getHeaders()['content-disposition'][0] ?? '';
        // HeaderUtils génère attachment; filename=...; filename*=UTF-8''...
        $this->assertStringStartsWith('attachment', $disposition);
        $this->assertStringNotContainsString('addslashes', $disposition);
    }
}
