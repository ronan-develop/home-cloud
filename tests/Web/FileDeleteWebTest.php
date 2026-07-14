<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Interface\StorageServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels — Suppression de fichier via l'interface web.
 *
 * TDD RED : ces tests doivent d'abord échouer, puis passer après implémentation.
 */
final class FileDeleteWebTest extends WebTestCase
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

    private function createFolder(string $name, User $owner): Folder
    {
        $folder = new Folder($name, $owner);
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

    private function csrfToken(string $folderId): string
    {
        $crawler = $this->client->request('GET', '/explorer?folder=' . $folderId);

        return $crawler->filter('.file-actions input[name="_token"]')->first()->attr('value');
    }

    public function testDeleteFileFlashesSuccessMessage(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('Docs', $user);
        $file = $this->createFile('rapport.txt', $folder, $user);
        $fileId = $file->getId();
        $this->em->clear();

        $this->login();
        $token = $this->csrfToken($folder->getId()->toRfc4122());

        $this->client->request('POST', '/files/' . $fileId . '/delete', ['_token' => $token]);
        $this->client->followRedirect();

        $this->assertSelectorTextContains('.flash-success', 'supprimé');
    }

    public function testDeleteFileFlashesErrorMessageWhenStorageFails(): void
    {
        $user = $this->createUser();
        $folder = $this->createFolder('Docs', $user);
        $file = $this->createFile('rapport.txt', $folder, $user);
        $fileId = $file->getId();
        $this->em->clear();

        $this->client->disableReboot();
        $this->login();
        $token = $this->csrfToken($folder->getId()->toRfc4122());

        $failingStorage = new class implements StorageServiceInterface {
            public function store(\Symfony\Component\HttpFoundation\File\UploadedFile $file): array
            {
                throw new \RuntimeException('not used');
            }

            public function delete(string $relativePath): void
            {
                throw new \RuntimeException('Disque hors service');
            }

            public function getAbsolutePath(string $relativePath): string
            {
                throw new \RuntimeException('not used');
            }
        };
        static::getContainer()->set(\App\Service\StorageService::class, $failingStorage);

        $this->client->request('POST', '/files/' . $fileId . '/delete', ['_token' => $token]);
        $this->client->followRedirect();

        $this->assertSelectorTextContains('.flash-error', 'suppression');

        // Le fichier ne doit pas avoir été supprimé de la base si le storage a échoué
        $found = $this->em->getRepository(File::class)->find($fileId);
        $this->assertNotNull($found, 'Le fichier ne doit pas être supprimé en base si le storage échoue');
    }
}
