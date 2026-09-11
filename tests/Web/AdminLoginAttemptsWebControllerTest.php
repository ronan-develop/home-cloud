<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\LoginAttempt;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TDD RED → GREEN : vue admin des tentatives de connexion échouées (#386),
 * même garde que les autres pages admin (AdminVoter → BroadcastAdminChecker).
 */
final class AdminLoginAttemptsWebControllerTest extends WebTestCase
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
        $conn->executeStatement('DELETE FROM login_attempts');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    public function testRejectsAnonymousUser(): void
    {
        $this->client->request('GET', '/admin/login-attempts');

        $this->assertResponseRedirects('/login');
    }

    public function testRejectsNonAdminEmail(): void
    {
        $this->createWebUser('pas-admin@example.com', 'secret123', 'Pas Admin');
        $this->loginAs('pas-admin@example.com');

        $this->client->request('GET', '/admin/login-attempts');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRendersEmptyStateWhenNoAttempts(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/login-attempts');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
        $this->assertStringContainsString('Aucune tentative', $crawler->text());
    }

    public function testAdminLayoutHasNavLinkToLoginAttempts(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('GET', '/admin/login-attempts');

        $this->assertSelectorExists('a[href="/admin/login-attempts"]');
    }

    public function testDisplaysRecentAttempt(): void
    {
        $this->em->persist(new LoginAttempt(hash('sha256', 'victim@example.com'), '203.0.113.7', 'Mozilla/5.0'));
        $this->em->flush();

        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/login-attempts');

        $this->assertStringContainsString('203.0.113.7', $crawler->filter('.hc-admin-table')->text());
    }

    public function testHighlightsSuspiciousEmailHash(): void
    {
        $emailHash = hash('sha256', 'attacker-target@example.com');
        for ($i = 0; $i < 5; ++$i) {
            $this->em->persist(new LoginAttempt($emailHash, '10.0.0.1', 'Mozilla/5.0'));
        }
        $this->em->flush();

        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/login-attempts');

        $this->assertStringContainsString(substr($emailHash, 0, 12), $crawler->filter('.hc-admin-suspicious')->text());
    }
}
