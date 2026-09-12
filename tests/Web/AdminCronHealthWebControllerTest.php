<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * TDD RED → GREEN : écran admin de santé cron Messenger (#377) — périmètre
 * recadré en commentaire du ticket : instance courante uniquement, cron
 * Messenger seulement (pas de stockage déjà couvert par #374/#376, pas
 * d'erreurs applicatives faute de canal de log exploitable).
 */
final class AdminCronHealthWebControllerTest extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
{
    use WebFixturesTrait;

    private EntityManagerInterface $em;
    private Connection $connection;
    private SenderInterface $failedSender;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->connection = $container->get('doctrine.dbal.default_connection');
        $this->failedSender = $container->get('messenger.transport.failed');

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();

        $this->connection->executeStatement('DELETE FROM messenger_messages');
    }

    private function insertFailedMessage(object $message): void
    {
        $this->failedSender->send(new Envelope($message));
    }

    public function testRejectsAnonymousUser(): void
    {
        $this->client->request('GET', '/admin/cron-health');

        $this->assertResponseRedirects('/login');
    }

    public function testRejectsNonAdminEmail(): void
    {
        $this->createWebUser('pas-admin@example.com', 'secret123', 'Pas Admin');
        $this->loginAs('pas-admin@example.com');

        $this->client->request('GET', '/admin/cron-health');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRendersPageForAdminUserWithNoFailures(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/cron-health');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
        $this->assertStringContainsString('0', $crawler->filter('.hc-admin-table, .hc-cron-health-summary')->text());
    }

    public function testAdminLayoutHasNavLinkToCronHealth(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('GET', '/admin/cron-health');

        $this->assertSelectorExists('a[href="/admin/cron-health"]');
    }

    public function testDisplaysFailedMessageClassAndErrorDetails(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->insertFailedMessage(new \App\Message\MediaProcessMessage('some-file-id'));

        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/cron-health');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('MediaProcessMessage', $crawler->html());
    }
}
