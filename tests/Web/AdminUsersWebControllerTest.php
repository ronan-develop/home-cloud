<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TDD RED → GREEN : page liste des users de l'espace admin (#374). Réservée
 * à l'admin whitelisté (AdminVoter → BroadcastAdminChecker), même garde que
 * BroadcastAdminWebController.
 */
final class AdminUsersWebControllerTest extends WebTestCase
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

    private function setCreatedAt(User $user, \DateTimeImmutable $createdAt): void
    {
        $prop = new \ReflectionProperty(User::class, 'createdAt');
        $prop->setAccessible(true);
        $prop->setValue($user, $createdAt);
    }

    public function testRejectsAnonymousUser(): void
    {
        $this->client->request('GET', '/admin/users');

        $this->assertResponseRedirects('/login');
    }

    public function testRejectsNonAdminEmail(): void
    {
        $this->createWebUser('pas-admin@example.com', 'secret123', 'Pas Admin');
        $this->loginAs('pas-admin@example.com');

        $this->client->request('GET', '/admin/users');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRendersPageForAdminUser(): void
    {
        $this->createAdmin();
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('GET', '/admin/users');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }

    public function testAdminLayoutHasNavToUsersAndBroadcast(): void
    {
        $this->createAdmin();
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('GET', '/admin/users');

        $this->assertSelectorExists('a[href="/admin/users"]');
        $this->assertSelectorExists('a[href="/admin/broadcast"]');
    }

    public function testListsAllUsersWithEmailAndCreatedAt(): void
    {
        $this->createAdmin();
        $this->createWebUser('alice@example.com', 'secret123', 'Alice');
        $this->createWebUser('bob@example.com', 'secret123', 'Bob');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/users');

        $this->assertStringContainsString('alice@example.com', $crawler->text());
        $this->assertStringContainsString('bob@example.com', $crawler->text());
    }

    public function testDisplaysStorageUsagePerUser(): void
    {
        $admin = $this->createAdmin();
        $this->createMediaFile($admin, size: 2048);
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/users');

        $this->assertStringContainsString('2 Ko', $crawler->text());
    }

    public function testDisplaysNotTrackedForLastLogin(): void
    {
        $this->createAdmin();
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/users');

        $this->assertStringContainsString('Non trackée', $crawler->text());
    }

    public function testExcludesGuestsFromTheList(): void
    {
        $this->createAdmin();
        $this->createWebUser('owner@example.com', 'secret123', 'Owner');
        $guest = $this->createWebUser('guest@example.com', 'secret123', 'Guest');
        $guest->markAsGuest();
        $this->em->flush();
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/users');

        $this->assertStringContainsString('owner@example.com', $crawler->text());
        $this->assertStringNotContainsString('guest@example.com', $crawler->text());
    }

    public function testUsersOrderedNewestFirst(): void
    {
        $this->createAdmin();

        $older = $this->createWebUser('older@example.com', 'secret123', 'Older');
        $this->setCreatedAt($older, new \DateTimeImmutable('2026-01-01'));

        $newer = $this->createWebUser('newer@example.com', 'secret123', 'Newer');
        $this->setCreatedAt($newer, new \DateTimeImmutable('2026-06-01'));

        $this->em->flush();
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/users');
        $text = $crawler->text();

        $this->assertGreaterThan(
            strpos($text, 'newer@example.com'),
            strpos($text, 'older@example.com'),
        );
    }
}
