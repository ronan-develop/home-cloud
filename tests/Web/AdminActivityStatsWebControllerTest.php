<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Album;
use App\Entity\Share;
use App\Entity\ShareLink;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * TDD RED → GREEN : statistiques d'activité de l'instance dans l'espace admin
 * (#388) — compteurs agrégés uniquement, jamais l'identité des invités.
 */
final class AdminActivityStatsWebControllerTest extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
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
        $conn->executeStatement('DELETE FROM share_links');
        $conn->executeStatement('DELETE FROM shares');
        $conn->executeStatement('DELETE FROM album_media');
        $conn->executeStatement('DELETE FROM albums');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createGuest(string $email): User
    {
        $guest = new User($email, 'Guest User');
        $guest->setPassword('irrelevant-hash');
        $guest->markAsGuest();
        $this->em->persist($guest);
        $this->em->flush();

        return $guest;
    }

    public function testRejectsAnonymousUser(): void
    {
        $this->client->request('GET', '/admin/activity-stats');

        $this->assertResponseRedirects('/login');
    }

    public function testRejectsNonAdminEmail(): void
    {
        $this->createWebUser('pas-admin@example.com', 'secret123', 'Pas Admin');
        $this->loginAs('pas-admin@example.com');

        $this->client->request('GET', '/admin/activity-stats');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRendersPageForAdminUserWithEmptyInstance(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/activity-stats');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
        $this->assertStringContainsString('0', $crawler->filter('.hc-admin-table')->text());
    }

    public function testAdminLayoutHasNavLinkToActivityStats(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('GET', '/admin/activity-stats');

        $this->assertSelectorExists('a[href="/admin/activity-stats"]');
    }

    public function testDisplaysCorrectCountersForInstanceActivity(): void
    {
        $owner = $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->createGuest('guest-1-stats@example.com');
        $this->createGuest('guest-2-stats@example.com');

        $this->createMediaFile($owner, 'photo1.jpg');
        $this->createMediaFile($owner, 'photo2.jpg');

        $album = new Album('Vacances', $owner);
        $this->em->persist($album);

        $guestForShare = $this->createGuest('guest-share-stats@example.com');
        $activeShare = new Share($owner, $guestForShare, Share::RESOURCE_FOLDER, Uuid::v7(), Share::PERMISSION_READ);
        $revokedShare = new Share($owner, $guestForShare, Share::RESOURCE_FOLDER, Uuid::v7(), Share::PERMISSION_READ);
        $revokedShare->revoke();
        $this->em->persist($activeShare);
        $this->em->persist($revokedShare);

        $activeLink = new ShareLink($owner, Share::RESOURCE_FILE, Uuid::v7(), 'selectoractivestats000000000000', hash('sha256', 'token1'), null);
        $revokedLink = new ShareLink($owner, Share::RESOURCE_FILE, Uuid::v7(), 'selectorrevokedstats00000000000', hash('sha256', 'token2'), null);
        $revokedLink->revoke();
        $this->em->persist($activeLink);
        $this->em->persist($revokedLink);

        $this->em->flush();

        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/activity-stats');

        $tableText = $crawler->filter('.hc-admin-table')->text();
        $this->assertStringContainsString('3', $tableText); // 3 guests au total
        $this->assertStringContainsString('2', $tableText); // 2 fichiers
        $this->assertStringContainsString('1', $tableText); // 1 album, 1 share actif, 1 lien actif
    }

    public function testNeverExposesGuestIdentityInResponse(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->createGuest('secret-guest-email@example.com');
        $this->em->flush();

        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/activity-stats');

        $this->assertStringNotContainsString('secret-guest-email@example.com', $crawler->html());
        $this->assertStringNotContainsString('Guest User', $crawler->html());
    }
}
