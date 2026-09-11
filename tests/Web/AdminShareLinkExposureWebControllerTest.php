<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\ShareLink;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * TDD RED → GREEN : surface d'exposition des liens de partage publics dans
 * l'espace admin (#387) — vue en lecture seule, liens actifs uniquement,
 * mise en avant de ceux créés il y a plus de 30 jours.
 */
final class AdminShareLinkExposureWebControllerTest extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
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
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function setCreatedAt(ShareLink $link, \DateTimeImmutable $createdAt): void
    {
        $ref = new \ReflectionProperty(ShareLink::class, 'createdAt');
        $ref->setValue($link, $createdAt);
    }

    public function testRejectsAnonymousUser(): void
    {
        $this->client->request('GET', '/admin/share-link-exposure');

        $this->assertResponseRedirects('/login');
    }

    public function testRejectsNonAdminEmail(): void
    {
        $this->createWebUser('pas-admin@example.com', 'secret123', 'Pas Admin');
        $this->loginAs('pas-admin@example.com');

        $this->client->request('GET', '/admin/share-link-exposure');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRendersPageForAdminUserWithEmptyInstance(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('GET', '/admin/share-link-exposure');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }

    public function testAdminLayoutHasNavLinkToShareLinkExposure(): void
    {
        $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $this->client->request('GET', '/admin/share-link-exposure');

        $this->assertSelectorExists('a[href="/admin/share-link-exposure"]');
    }

    public function testDisplaysActiveLinkWithResourceNameAndOwnerEmail(): void
    {
        $owner = $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $folder = new Folder('Photos', $owner);
        $this->em->persist($folder);
        $this->em->flush();

        $link = new ShareLink($owner, Share::RESOURCE_FOLDER, $folder->getId(), 'selectordisplayname0000000000000', hash('sha256', 'token'), null);
        $this->em->persist($link);
        $this->em->flush();

        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/share-link-exposure');

        $tableText = $crawler->filter('.hc-admin-table')->text();
        $this->assertStringContainsString('Photos', $tableText);
        $this->assertStringContainsString($_ENV['BROADCAST_ADMIN_EMAIL'], $tableText);
    }

    public function testDisplaysDeletedResourceFallbackInsteadOfCrashing(): void
    {
        $owner = $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');
        $link = new ShareLink($owner, Share::RESOURCE_FOLDER, Uuid::v7(), 'selectordeletedresource00000000', hash('sha256', 'token'), null);
        $this->em->persist($link);
        $this->em->flush();

        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/share-link-exposure');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Ressource supprimée', $crawler->filter('.hc-admin-table')->text());
    }

    public function testExcludesRevokedAndExpiredLinksFromList(): void
    {
        $owner = $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');

        $revoked = new ShareLink($owner, Share::RESOURCE_FILE, Uuid::v7(), 'selectorrevokedexposure00000000', hash('sha256', 'token1'), null);
        $revoked->revoke();

        $expired = new ShareLink($owner, Share::RESOURCE_FILE, Uuid::v7(), 'selectorexpiredexposure00000000', hash('sha256', 'token2'), new \DateTimeImmutable('-1 day'));

        $this->em->persist($revoked);
        $this->em->persist($expired);
        $this->em->flush();

        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/share-link-exposure');

        $this->assertStringNotContainsString('selectorrevokedexposure', $crawler->html());
        $this->assertStringNotContainsString('selectorexpiredexposure', $crawler->html());
    }

    public function testMarksLinkOlderThanThirtyDaysAsOld(): void
    {
        $owner = $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');

        $old = new ShareLink($owner, Share::RESOURCE_FILE, Uuid::v7(), 'selectoroldmarkexposure00000000', hash('sha256', 'token'), null);
        $this->em->persist($old);
        $this->em->flush();
        $this->setCreatedAt($old, new \DateTimeImmutable('-31 days'));
        $this->em->flush();

        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/share-link-exposure');

        $this->assertSelectorExists('.hc-admin-row-old');
    }

    public function testDoesNotMarkRecentLinkAsOld(): void
    {
        $owner = $this->createWebUser($_ENV['BROADCAST_ADMIN_EMAIL'], 'secret123', 'Admin');

        $recent = new ShareLink($owner, Share::RESOURCE_FILE, Uuid::v7(), 'selectorrecentmarkexposure000000', hash('sha256', 'token'), null);
        $this->em->persist($recent);
        $this->em->flush();

        $this->loginAs($_ENV['BROADCAST_ADMIN_EMAIL']);

        $crawler = $this->client->request('GET', '/admin/share-link-exposure');

        $this->assertSelectorNotExists('.hc-admin-row-old');
    }
}
