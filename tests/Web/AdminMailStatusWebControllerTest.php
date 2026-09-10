<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Service\MailerConnectivityChecker;
use App\Service\SmtpConnectivityProberInterface;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * TDD RED → GREEN : statut envoi d'emails dans l'espace admin (#385), suite
 * à #378 où un mot de passe SMTP expiré était resté invisible des semaines.
 * Même garde que les autres pages admin (AdminVoter → BroadcastAdminChecker).
 */
final class AdminMailStatusWebControllerTest extends WebTestCase
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

    public function testRejectsAnonymousUser(): void
    {
        $this->client->request('GET', '/admin/mail-status');

        $this->assertResponseRedirects('/login');
    }

    public function testRejectsNonAdminEmail(): void
    {
        $this->createWebUser('pas-admin@example.com', 'secret123', 'Pas Admin');
        $this->loginAs('pas-admin@example.com');

        $this->client->request('GET', '/admin/mail-status');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRendersPageForAdminUser(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('GET', '/admin/mail-status');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }

    public function testAdminLayoutHasNavLinkToMailStatus(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('GET', '/admin/mail-status');

        $this->assertSelectorExists('a[href="/admin/mail-status"]');
    }

    public function testDisplaysMailerDsnNotConfiguredWhenNullDsn(): void
    {
        // MAILER_DSN=null://null en environnement de test (.env.test.local) : cas déjà réel, pas de mock nécessaire.
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/mail-status');

        $this->assertStringContainsString('Non configuré', $crawler->text());
    }

    private function mockConnectivityCheckerWithProber(SmtpConnectivityProberInterface $prober): void
    {
        // KernelBrowser reboote le kernel (et son container) à chaque requête par
        // défaut : sans disableReboot(), le set() ci-dessous serait écrasé par la
        // requête suivante (loginAs() a déjà fait 3 requêtes avant cet appel).
        $this->client->disableReboot();

        $checker = new MailerConnectivityChecker('smtp://user:pass@example.test:465', $prober);
        static::getContainer()->set(MailerConnectivityChecker::class, $checker);
    }

    public function testDisplaysConnectivityErrorMessageWhenCheckFails(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $prober = $this->createStub(SmtpConnectivityProberInterface::class);
        $prober->method('probe')->willThrowException(new \RuntimeException('535 Incorrect authentication data'));
        $this->mockConnectivityCheckerWithProber($prober);

        $crawler = $this->client->request('GET', '/admin/mail-status');

        $this->assertStringContainsString('535 Incorrect authentication data', $crawler->text());
    }

    public function testDisplaysConnectivitySuccessWhenCheckSucceeds(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $prober = $this->createStub(SmtpConnectivityProberInterface::class);
        $this->mockConnectivityCheckerWithProber($prober);

        $crawler = $this->client->request('GET', '/admin/mail-status');

        $this->assertStringContainsString('OK', $crawler->text());
    }
}
