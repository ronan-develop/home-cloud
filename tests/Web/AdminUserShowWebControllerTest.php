<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Folder;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * TDD RED → GREEN : page de détail d'un user dans l'espace admin (#375).
 * Réservée à l'admin whitelisté (AdminVoter → BroadcastAdminChecker).
 */
final class AdminUserShowWebControllerTest extends WebTestCase
{
    use WebFixturesTrait;

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

    private function createAdmin(): User
    {
        return $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
    }

    public function testRejectsAnonymousUser(): void
    {
        $target = $this->createWebUser('target@example.com', 'secret123', 'Target');

        $this->client->request('GET', "/admin/users/{$target->getId()}");

        $this->assertResponseRedirects('/login');
    }

    public function testRejectsNonAdmin(): void
    {
        $target = $this->createWebUser('target@example.com', 'secret123', 'Target');
        $this->createWebUser('pas-admin@example.com', 'secret123', 'Pas Admin');
        $this->loginAs('pas-admin@example.com');

        $this->client->request('GET', "/admin/users/{$target->getId()}");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testReturns404ForGuestTarget(): void
    {
        $this->createAdmin();
        $guest = $this->createWebUser('guest@example.com', 'secret123', 'Guest');
        $guest->markAsGuest();
        $this->em->flush();
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('GET', "/admin/users/{$guest->getId()}");

        $this->assertResponseStatusCodeSame(404);
    }

    public function testReturns404ForUnknownId(): void
    {
        $this->createAdmin();
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('GET', '/admin/users/' . Uuid::v7());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testShowsUserEmailAndActiveStatus(): void
    {
        $this->createAdmin();
        $target = $this->createWebUser('target@example.com', 'secret123', 'Target');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', "/admin/users/{$target->getId()}");

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('target@example.com', $crawler->text());
        $this->assertStringContainsString('Actif', $crawler->text());
    }

    public function testShowsInactiveStatusForDeactivatedUser(): void
    {
        $this->createAdmin();
        $target = $this->createWebUser('target@example.com', 'secret123', 'Target');
        $target->deactivate();
        $this->em->flush();
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', "/admin/users/{$target->getId()}");

        $this->assertStringContainsString('Désactivé', $crawler->text());
    }

    public function testStorageBreakdownByRootFolderWithRecursiveTotal(): void
    {
        $this->createAdmin();
        $target = $this->createWebUser('target@example.com', 'secret123', 'Target');

        $root = new Folder('Photos', $target);
        $this->em->persist($root);
        $sub = new Folder('Vacances', $target, $root);
        $this->em->persist($sub);
        $this->em->flush();

        $this->createFileInFolder($target, $root, 'a.jpg', 1000);
        $this->createFileInFolder($target, $sub, 'b.jpg', 1000);

        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);
        $crawler = $this->client->request('GET', "/admin/users/{$target->getId()}");

        $this->assertStringContainsString('Photos', $crawler->text());
        $this->assertStringContainsString('1,95 Ko', $crawler->text());
    }

    private function createFileInFolder(User $owner, Folder $folder, string $name, int $size): void
    {
        $file = new \App\Entity\File($name, 'application/octet-stream', $size, 'irrelevant/' . $name, $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();
    }
}
