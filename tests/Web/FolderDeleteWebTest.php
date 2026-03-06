<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels — Suppression de dossier via l'interface web.
 *
 * TDD RED : ces tests doivent d'abord échouer, puis passer après implémentation.
 */
final class FolderDeleteWebTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
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
        $file = new File($name, 'text/plain', 42, 'test/' . uniqid() . '.txt', $folder, $owner, false);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    // ── Suppression deleteContents=true ─────────────────────────────────────

    public function testDeleteFolderWithDeleteContentsTrueDeletesFolder(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('ToDelete', $user);
        $folderId = $folder->getId();
        $this->em->clear();

        $this->login();

        $this->client->request('POST', '/folders/' . $folderId . '/delete', [
            'delete_contents' => '1',
        ]);

        $this->assertResponseRedirects();
        $this->em->clear();
        $found = $this->em->getRepository(Folder::class)->find($folderId);
        $this->assertNull($found, 'Le dossier doit être supprimé');
    }

    // ── Suppression deleteContents=false ────────────────────────────────────

    public function testDeleteFolderWithDeleteContentsFalseMovesFilesToUploads(): void
    {
        $user = $this->createUser();
        $parent = $this->createFolder('Parent', $user);
        $child = $this->createFolder('Child', $user, $parent);
        $file1 = $this->createFile('one.txt', $parent, $user);
        $file2 = $this->createFile('two.txt', $child, $user);
        $parentId = $parent->getId();
        $file1Id  = $file1->getId();
        $file2Id  = $file2->getId();
        $userId   = $user->getId();
        $this->em->clear();

        $this->login();

        $this->client->request('POST', '/folders/' . $parentId . '/delete', [
            'delete_contents' => '0',
        ]);

        $this->assertResponseRedirects();
        $this->em->clear();

        // Dossier supprimé
        $this->assertNull($this->em->getRepository(Folder::class)->find($parentId));

        // Fichiers déplacés dans Uploads
        $user = $this->em->getRepository(User::class)->find($userId);
        $uploads = $this->em->getRepository(Folder::class)->findOneBy(['name' => 'Uploads', 'owner' => $user]);
        $this->assertNotNull($uploads, 'Uploads doit exister');

        $f1 = $this->em->getRepository(File::class)->find($file1Id);
        $f2 = $this->em->getRepository(File::class)->find($file2Id);

        $this->assertNotNull($f1);
        $this->assertNotNull($f2);
        $this->assertSame((string) $uploads->getId(), (string) $f1->getFolder()->getId());
        $this->assertSame((string) $uploads->getId(), (string) $f2->getFolder()->getId());
    }

    // ── Redirection ─────────────────────────────────────────────────────────

    public function testDeleteFolderRedirectsToFolderIdIfProvided(): void
    {
        $user = $this->createUser();
        $parent = $this->createFolder('Parent', $user);
        $folder = $this->createFolder('ToDelete', $user, $parent);
        $folderId = $folder->getId();
        $parentId = $parent->getId();
        $this->em->clear();

        $this->login();

        $this->client->request('POST', '/folders/' . $folderId . '/delete', [
            'delete_contents' => '1',
            'redirect_folder_id' => (string) $parentId,
        ]);

        $this->assertResponseRedirects('/?folder=' . $parentId);
    }

    public function testDeleteFolderRedirectsToRootIfNoRedirect(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('ToDelete', $user);
        $folderId = $folder->getId();
        $this->em->clear();

        $this->login();

        $this->client->request('POST', '/folders/' . $folderId . '/delete', [
            'delete_contents' => '1',
        ]);

        $this->assertResponseRedirects('/');
    }

    // ── Sécurité ─────────────────────────────────────────────────────────────

    public function testDeleteFolderRequiresAuthentication(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('Private', $user);
        $folderId = $folder->getId();
        $this->em->clear();

        $this->client->request('POST', '/folders/' . $folderId . '/delete', [
            'delete_contents' => '1',
        ]);

        // Redirige vers login (non authentifié)
        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('login', $location ?? '');
    }

    public function testDeleteFolderByNonOwnerThrows403(): void
    {
        $owner = $this->createUser('owner@example.com');
        $other = $this->createUser('other@example.com');
        $folder = $this->createFolder('Owned', $owner);
        $folderId = $folder->getId();
        $this->em->clear();

        $this->login('other@example.com');

        $this->client->request('POST', '/folders/' . $folderId . '/delete', [
            'delete_contents' => '1',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    // ── UI ───────────────────────────────────────────────────────────────────

    public function testDeleteButtonPresentInFolderCard(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('MyFolder', $user);
        $folderId = $folder->getId();
        $this->em->clear();

        $this->login();
        $this->client->request('GET', '/');
        $this->assertSelectorExists('[data-testid="delete-folder-btn-' . $folderId . '"]');
    }

    public function testDeleteFolderModalPresentInLayout(): void
    {
        $this->createUser();
        $this->em->clear();

        $this->login();
        $this->client->request('GET', '/');

        $this->assertSelectorExists('#delete-folder-modal');
        $this->assertSelectorExists('[data-testid="delete-folder-recursive-btn"]');
        $this->assertSelectorExists('[data-testid="delete-folder-keep-files-btn"]');
    }
}
