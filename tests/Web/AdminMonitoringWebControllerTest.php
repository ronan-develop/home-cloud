<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;

/**
 * TDD RED → GREEN : écran admin de monitoring multi-instances (#376) — vue
 * agrégée d'une ligne par instance (nb users, stockage, révision git,
 * joignabilité), sur le modèle de #283 (broadcast).
 */
final class AdminMonitoringWebControllerTest extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
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
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    public function testRejectsAnonymousUser(): void
    {
        $this->client->request('GET', '/admin/monitoring');

        $this->assertResponseRedirects('/login');
    }

    public function testRejectsNonAdminEmail(): void
    {
        $this->createWebUser('pas-admin@example.com', 'secret123', 'Pas Admin');
        $this->loginAs('pas-admin@example.com');

        $this->client->request('GET', '/admin/monitoring');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRendersPageForAdminUser(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/monitoring');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
        $this->assertSelectorExists('.hc-admin-table');
    }

    public function testAdminLayoutHasNavLinkToMonitoring(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('GET', '/admin/monitoring');

        $this->assertSelectorExists('a[href="/admin/monitoring"]');
    }
}
