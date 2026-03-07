<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DeleteFolderEmptyFlagTest extends WebTestCase
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

    public function testEmptyAndNonEmptyFolderDeleteButtonDataEmptyFlag(): void
    {
        $user = $this->createUser();
        $empty = $this->createFolder('EmptyFolder', $user);
        $nonEmpty = $this->createFolder('NonEmptyFolder', $user);
        $this->createFile('file.txt', $nonEmpty, $user);

        $emptyId = $empty->getId();
        $nonEmptyId = $nonEmpty->getId();
        $this->em->clear();

        $this->login();
        $crawler = $this->client->request('GET', '/');

        // DEBUG: dump homepage HTML for inspection
        file_put_contents('/tmp/homepage_debug.html', $this->client->getResponse()->getContent());

        $this->assertSelectorExists('[data-testid="delete-folder-btn-' . $emptyId . '"]');
        $btnEmpty = $crawler->filter('[data-testid="delete-folder-btn-' . $emptyId . '"]');
        $this->assertSame('1', $btnEmpty->attr('data-empty'));

        $this->assertSelectorExists('[data-testid="delete-folder-btn-' . $nonEmptyId . '"]');
        $btnNonEmpty = $crawler->filter('[data-testid="delete-folder-btn-' . $nonEmptyId . '"]');
        $this->assertSame('0', $btnNonEmpty->attr('data-empty'));
    }
}
