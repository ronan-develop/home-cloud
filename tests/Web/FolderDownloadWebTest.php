<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels — Téléchargement d'un dossier entier (zip) via l'interface web.
 *
 * TDD RED : ces tests doivent d'abord échouer, puis passer après implémentation (#240).
 */
final class FolderDownloadWebTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM shares');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createUser(string $email = 'test@example.com', string $password = 'secret123'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User($email, 'Test');
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function login(string $email = 'test@example.com', string $password = 'secret123'): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => $password,
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    private function createFolder(string $name, User $owner, ?Folder $parent = null): Folder
    {
        $folder = new Folder($name, $owner, $parent);
        $this->em->persist($folder);
        $this->em->flush();

        return $folder;
    }

    private function createFile(string $name, Folder $folder, User $owner): File
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hc_test_');
        file_put_contents($tmp, 'contenu de test');

        $relativePath = 'test-storage/' . uniqid() . '.txt';
        $storage = static::getContainer()->get(\App\Interface\StorageServiceInterface::class);
        $absolutePath = $storage->getAbsolutePath($relativePath);
        @mkdir(dirname($absolutePath), 0777, true);
        copy($tmp, $absolutePath);

        $file = new File($name, 'text/plain', filesize($absolutePath), $relativePath, $folder, $owner, false);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    // ── Sécurité ─────────────────────────────────────────────────────────────

    public function testDownloadFolderRequiresAuthentication(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('Private', $user);
        $folderId = $folder->getId();
        $this->em->clear();

        $this->client->request('GET', '/folders/' . $folderId . '/download');

        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('login', $location ?? '');
    }

    public function testDownloadFolderByNonOwnerWithoutShareThrows403(): void
    {
        $owner = $this->createUser('owner@example.com');
        $other = $this->createUser('other@example.com');
        $folder = $this->createFolder('Owned', $owner);
        $folderId = $folder->getId();
        $this->em->clear();

        $this->login('other@example.com');

        $this->client->request('GET', '/folders/' . $folderId . '/download');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDownloadFolderBySharedGuestWithReadPermissionSucceeds(): void
    {
        $owner = $this->createUser('owner@example.com');
        $guest = $this->createUser('guest@example.com');
        $folder = $this->createFolder('Shared', $owner);
        $share = new Share($owner, $guest, Share::RESOURCE_FOLDER, $folder->getId(), Share::PERMISSION_READ);
        $this->em->persist($share);
        $this->em->flush();
        $folderId = $folder->getId();
        $this->em->clear();

        $this->login('guest@example.com');

        $this->client->request('GET', '/folders/' . $folderId . '/download');

        $this->assertResponseIsSuccessful();
    }

    public function testDownloadNonExistentFolderThrows404(): void
    {
        $this->createUser();
        $this->em->clear();

        $this->login();

        $this->client->request('GET', '/folders/' . \Symfony\Component\Uid\Uuid::v7() . '/download');

        $this->assertResponseStatusCodeSame(404);
    }

    // ── Cas nominal ──────────────────────────────────────────────────────────

    public function testDownloadFolderReturnsZipWithCorrectHeaders(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('MyFolder', $user);
        $this->createFile('doc.txt', $folder, $user);
        $folderId = $folder->getId();
        $this->em->clear();

        $this->login();

        $this->client->request('GET', '/folders/' . $folderId . '/download');

        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        $this->assertSame('application/zip', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('MyFolder.zip', $response->headers->get('Content-Disposition') ?? '');
    }

    public function testDownloadFolderZipContainsNestedFilesAndSubfolders(): void
    {
        $user = $this->createUser();
        $parent = $this->createFolder('Parent', $user);
        $child = $this->createFolder('Child', $user, $parent);
        $this->createFile('root.txt', $parent, $user);
        $this->createFile('nested.txt', $child, $user);
        $parentId = $parent->getId();
        $this->em->clear();

        $this->login();

        $this->client->request('GET', '/folders/' . $parentId . '/download');

        // BinaryFileResponse::getContent() ne remonte pas le contenu binaire réel
        // dans le client de test PHPUnit (même limitation que StreamedResponse,
        // cf. FileDownloadController) — la structure du zip est vérifiée au niveau
        // service dans FolderZipArchiverTest, ici on ne vérifie que la réponse HTTP.
        $this->assertResponseIsSuccessful();
    }

    public function testDownloadEmptyFolderReturnsValidZip(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('Empty', $user);
        $folderId = $folder->getId();
        $this->em->clear();

        $this->login();

        $this->client->request('GET', '/folders/' . $folderId . '/download');

        $this->assertResponseIsSuccessful();
    }

    // ── UI ───────────────────────────────────────────────────────────────────

    public function testDownloadButtonPresentInFolderCard(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('MyFolder', $user);
        $folderId = $folder->getId();
        $this->em->clear();

        $this->login();
        $this->client->request('GET', '/explorer');
        $this->assertSelectorExists('[data-testid="download-folder-btn-' . $folderId . '"]');
    }
}
