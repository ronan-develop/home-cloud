<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Service\DefaultFolderService;
use App\Tests\AuthenticatedApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileTest extends AuthenticatedApiTestCase
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

        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/files/'.$file->getId());

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
        $this->createUser();
        $this->createAuthenticatedClient()->request('GET', '/api/v1/files/00000000-0000-0000-0000-000000000000');

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

        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/files', [
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

        $response = $this->createAuthenticatedClient()->request('GET', '/api/v1/files?folderId='.$folder1->getId(), [
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

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
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

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
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

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
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

        $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'json' => ['ownerId' => (string) $user->getId()],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testPostFileReturns404WhenFolderIdNotFound(): void
    {
        $user = $this->createUser();

        $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
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

        $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
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

        $this->createAuthenticatedClient()->request('DELETE', '/api/v1/files/'.$file->getId());

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteFileAlsoRemovesPhysicalFile(): void
    {
        $user = $this->createUser();
        $folder = new Folder('Temp', $user);
        $this->em->persist($folder);

        // Upload réel pour avoir un fichier sur disque
        $client = $this->createAuthenticatedClient();
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

    public function testDownloadFileReturns200WithCorrectHeaders(): void
    {
        $user = $this->createUser();

        $client = $this->createAuthenticatedClient();
        $response = $client->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('binary-content', 'doc.txt')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);
        $this->assertResponseStatusCodeSame(201);
        $id = $response->toArray()['id'];

        $download = $client->request('GET', '/api/v1/files/'.$id.'/download');

        // BinaryFileResponse retourne body vide dans PHPUnit — on vérifie status + headers
        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('x-content-type-options', 'nosniff');
        $this->assertStringContainsString('attachment', $download->getHeaders()['content-disposition'][0] ?? '');
    }

    public function testDownloadFileReturns404WhenNotFound(): void
    {
        $this->createUser();
        $this->createAuthenticatedClient()->request('GET', '/api/v1/files/00000000-0000-0000-0000-000000000000/download');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDeleteFileReturns404WhenNotFound(): void
    {
        $this->createUser();
        $this->createAuthenticatedClient()->request('DELETE', '/api/v1/files/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(404);
    }

    // --- Sécurité : extensions serveur bloquées ---

    public function testPostFileReturns400WhenPhpExtension(): void
    {
        $user = $this->createUser();

        $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
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

        $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
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

        $client = $this->createAuthenticatedClient();
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

    // --- Sécurité : sanitisation originalName ---

    public function testPostFileSanitizesControlCharsInOriginalName(): void
    {
        $user = $this->createUser();

        $tmp = tempnam(sys_get_temp_dir(), 'hc_');
        file_put_contents($tmp, 'content');
        // Nom avec null byte + newline + tab + contenu normal
        $maliciousName = "mon\x00fichier\x0Adangerous\x09.txt";
        $uploadedFile = new UploadedFile($tmp, $maliciousName, 'text/plain', null, true);

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $uploadedFile],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $originalName = $response->toArray()['originalName'];
        $this->assertStringNotContainsString("\x00", $originalName);
        $this->assertStringNotContainsString("\n", $originalName);
        $this->assertStringNotContainsString("\t", $originalName);
        $this->assertSame('monfichierdangerous.txt', $originalName);
    }

    // --- Sécurité : validation newFolderName ---

    public function testPostFileReturns404WhenNewFolderNameTooLong(): void
    {
        $user = $this->createUser();

        $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('data', 'file.txt')],
                'parameters' => [
                    'ownerId' => (string) $user->getId(),
                    'newFolderName' => str_repeat('a', 256),
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testPostFileReturns404WhenNewFolderNameIsBlank(): void
    {
        $user = $this->createUser();

        $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('data', 'file.txt')],
                'parameters' => [
                    'ownerId' => (string) $user->getId(),
                    'newFolderName' => '   ',
                ],
            ],
        ]);

        // '   ' → trim() → '' → InvalidArgumentException → 404
        $this->assertResponseStatusCodeSame(404);
    }

    // --- Stockage au repos (Phase 8 : fichiers en clair) ---

    public function testPlainFileIsReadableOnDisk(): void
    {
        $user = $this->createUser();
        $content = 'contenu en clair très secret';

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile($content, 'secret.txt')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $path = $response->toArray()['path'];

        $storagePath = static::getContainer()->getParameter('app.storage_dir').'/'.$path;
        $this->assertFileExists($storagePath);

        // Phase 8 : fichiers ordinaires stockés en clair sur disque
        $diskContent = file_get_contents($storagePath);
        $this->assertStringContainsString($content, $diskContent, 'Un fichier ordinaire doit être lisible en clair sur disque');
    }

    public function testSvgFileIsAccepted(): void
    {
        $user = $this->createUser();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect width="10" height="10"/></svg>';

        $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile($svg, 'image.svg')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        // SVG accepté (neutralisé — stocké en .bin) — plus bloqué
        $this->assertResponseStatusCodeSame(201);
    }

    public function testHtmlFileIsAccepted(): void
    {
        $user = $this->createUser();
        $html = '<html><body><script>alert(document.cookie)</script></body></html>';

        $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile($html, 'page.html')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        // HTML accepté (neutralisé — stocké en .bin)
        $this->assertResponseStatusCodeSame(201);
    }

    // --- Phase 8 : neutralisation ciblée (RED) ---

    /** Fichiers ordinaires stockés tels quels sur disque (pas chiffrés) */
    public function testPlainFileIsStoredAsIsOnDisk(): void
    {
        $user = $this->createUser();
        $content = 'contenu en clair ordinaire';

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile($content, 'document.txt')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $path = $response->toArray()['path'];
        $storagePath = static::getContainer()->getParameter('app.storage_dir').'/'.$path;
        $this->assertFileExists($storagePath);
        $this->assertStringContainsString($content, file_get_contents($storagePath), 'Un fichier ordinaire doit être lisible en clair sur disque');
    }

    /** Fichiers neutralisés stockés avec extension .bin sur disque */
    public function testSvgFileIsStoredAsBinOnDisk(): void
    {
        $user = $this->createUser();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><rect/></svg>';

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile($svg, 'image.svg')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $path = $response->toArray()['path'];
        $this->assertStringEndsWith('.bin', $path, 'Un SVG doit être stocké avec extension .bin');
    }

    /** Fichiers neutralisés : le download restitue le nom et MIME d'origine */
    public function testNeutralizedFileDownloadRestoresOriginalName(): void
    {
        $user = $this->createUser();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>';

        $upload = $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile($svg, 'image.svg')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $id = $upload->toArray()['id'];

        $download = $this->createAuthenticatedClient()->request('GET', '/api/v1/files/'.$id.'/download');
        $this->assertResponseStatusCodeSame(200);
        $this->assertStringContainsString('image.svg', $download->getHeaders()['content-disposition'][0] ?? '');
    }

    /** Scripts neutralisés (.sh) stockés en .bin */
    public function testShellScriptIsNeutralizedAsBin(): void
    {
        $user = $this->createUser();

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('#!/bin/bash\nrm -rf /', 'danger.sh')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $path = $response->toArray()['path'];
        $this->assertStringEndsWith('.bin', $path, 'Un .sh doit être stocké en .bin');
    }

    /** Scripts Python neutralisés (.py) stockés en .bin */
    public function testPythonScriptIsNeutralizedAsBin(): void
    {
        $user = $this->createUser();

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('import os; os.system("rm -rf /")', 'script.py')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $path = $response->toArray()['path'];
        $this->assertStringEndsWith('.bin', $path, 'Un .py doit être stocké en .bin');
    }

    /** PHP reste bloqué — 400 */
    public function testPhpFileRemainsBlocked(): void
    {
        $user = $this->createUser();

        $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('<?php system($_GET["cmd"]); ?>', 'shell.php')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    /** EXE reste bloqué — 400 */
    public function testExeFileRemainsBlocked(): void
    {
        $user = $this->createUser();

        $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('MZ payload', 'malware.exe')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    /** is_neutralized = true en DB pour un SVG */
    public function testSvgFileIsMarkedNeutralizedInDb(): void
    {
        $user = $this->createUser();

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('<svg/>', 'icon.svg')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $id = $response->toArray()['id'];

        $file = $this->em->getRepository(\App\Entity\File::class)->find($id);
        $this->assertTrue($file->isNeutralized(), 'Un SVG doit être marqué is_neutralized = true en DB');
    }

    /** is_neutralized = false en DB pour un fichier ordinaire */
    public function testPlainFileIsNotMarkedNeutralized(): void
    {
        $user = $this->createUser();

        $response = $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('hello', 'notes.txt')],
                'parameters' => ['ownerId' => (string) $user->getId()],
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $id = $response->toArray()['id'];

        $file = $this->em->getRepository(\App\Entity\File::class)->find($id);
        $this->assertFalse($file->isNeutralized(), 'Un .txt ne doit pas être marqué neutralisé');
    }

    // --- Sécurité : ownership cross-user ---

    public function testPostFileReturns404WhenFolderBelongsToAnotherUser(): void
    {
        $owner = $this->createUser();

        // Créer un second user avec son propre folder
        $other = new User('other@example.com', 'Other');
        $hasher = static::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class);
        $other->setPassword($hasher->hashPassword($other, 'pass'));
        $this->em->persist($other);
        $otherFolder = new Folder('Dossier Autre', $other);
        $this->em->persist($otherFolder);
        $this->em->flush();

        $this->createAuthenticatedClient()->request('POST', '/api/v1/files', [
            'extra' => [
                'files' => ['file' => $this->makeTempFile('data', 'file.txt')],
                'parameters' => [
                    'ownerId' => (string) $owner->getId(),
                    'folderId' => (string) $otherFolder->getId(),
                ],
            ],
        ]);

        // Le folder existe mais appartient à un autre user → 404 (même message que "not found")
        $this->assertResponseStatusCodeSame(404);
    }
}
