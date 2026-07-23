<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MyReceivedSharesWebTest extends WebTestCase
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
        $conn->executeStatement('DELETE FROM shares');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createGuestUser(string $email): User
    {
        $guest = $this->createWebUser($email, 'secret123', 'Guest User');
        $guest->markAsGuest();
        $this->em->flush();

        return $guest;
    }

    private function createFolder(User $owner, string $name = 'Documents'): Folder
    {
        $folder = new Folder($name, $owner);
        $this->em->persist($folder);
        $this->em->flush();

        return $folder;
    }

    public function testMyReceivedSharesPageRequiresLogin(): void
    {
        $this->client->request('GET', '/mes-partages');

        $this->assertResponseRedirects('/login');
    }

    public function testMyReceivedSharesPageListsActiveIncomingShares(): void
    {
        $owner = $this->createWebUser('owner-my-shares@example.com', 'secret123', 'Owner User');
        $guest = $this->createGuestUser('guest-my-shares@example.com');
        $folder = $this->createFolder($owner, 'Partage reçu');
        $share = new Share($owner, $guest, Share::RESOURCE_FOLDER, $folder->getId(), Share::PERMISSION_READ);
        $this->em->persist($share);
        $this->em->flush();

        $this->loginAs('guest-my-shares@example.com');

        $crawler = $this->client->request('GET', '/mes-partages');

        $this->assertResponseIsSuccessful();
        $text = $crawler->filter('body')->text();
        $this->assertStringContainsString('Partage reçu', $text);
        $this->assertStringContainsString('Owner User', $text);
    }

    public function testMyReceivedSharesPageShowsResourceTypeOwnerPermissionAndExpiry(): void
    {
        $owner = $this->createWebUser('owner-details@example.com', 'secret123', 'Owner Details');
        $guest = $this->createGuestUser('guest-details@example.com');
        $folder = $this->createFolder($owner, 'Dossier détaillé');
        $expiresAt = new \DateTimeImmutable('+7 days');
        $share = new Share($owner, $guest, Share::RESOURCE_FOLDER, $folder->getId(), Share::PERMISSION_WRITE, $expiresAt);
        $this->em->persist($share);
        $this->em->flush();

        $this->loginAs('guest-details@example.com');

        $crawler = $this->client->request('GET', '/mes-partages');
        $this->assertResponseIsSuccessful();

        $row = $crawler->filter('[data-testid="share-row-incoming"]')->text();
        $this->assertStringContainsString('Dossier détaillé', $row);
        $this->assertStringContainsString('Owner Details', $row);
        $this->assertStringContainsString('lecture/écriture', $row);
        $this->assertStringContainsString($expiresAt->format('d/m/Y'), $row);
    }

    public function testMyReceivedSharesPageExcludesExpiredShares(): void
    {
        $owner = $this->createWebUser('owner-expired@example.com', 'secret123', 'Owner Expired');
        $guest = $this->createGuestUser('guest-expired-page@example.com');
        $folder = $this->createFolder($owner, 'Dossier expiré');
        $share = new Share(
            $owner,
            $guest,
            Share::RESOURCE_FOLDER,
            $folder->getId(),
            Share::PERMISSION_READ,
            new \DateTimeImmutable('-1 day'),
        );
        $this->em->persist($share);
        $this->em->flush();

        $this->loginAs('guest-expired-page@example.com');

        $crawler = $this->client->request('GET', '/mes-partages');
        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('Dossier expiré', $crawler->filter('body')->text());
    }

    public function testMyReceivedSharesPageExcludesRevokedShares(): void
    {
        $owner = $this->createWebUser('owner-revoked@example.com', 'secret123', 'Owner Revoked');
        $guest = $this->createGuestUser('guest-revoked-page@example.com');
        $folder = $this->createFolder($owner, 'Dossier révoqué');
        $share = new Share($owner, $guest, Share::RESOURCE_FOLDER, $folder->getId(), Share::PERMISSION_READ);
        $share->revoke();
        $this->em->persist($share);
        $this->em->flush();

        $this->loginAs('guest-revoked-page@example.com');

        $crawler = $this->client->request('GET', '/mes-partages');
        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('Dossier révoqué', $crawler->filter('body')->text());
    }

    public function testMyReceivedSharesPageShowsEmptyStateWhenNoShares(): void
    {
        $this->createGuestUser('guest-empty@example.com');
        $this->loginAs('guest-empty@example.com');

        $crawler = $this->client->request('GET', '/mes-partages');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="my-shares-empty"]');
    }

    public function testGuestOnlySeesTheirOwnReceivedShares(): void
    {
        $owner = $this->createWebUser('owner-isolation@example.com', 'secret123', 'Owner Isolation');
        $guestA = $this->createGuestUser('guest-a-isolation@example.com');
        $guestB = $this->createGuestUser('guest-b-isolation@example.com');

        $folderA = $this->createFolder($owner, 'Dossier de A');
        $folderB = $this->createFolder($owner, 'Dossier de B');

        $shareA = new Share($owner, $guestA, Share::RESOURCE_FOLDER, $folderA->getId(), Share::PERMISSION_READ);
        $shareB = new Share($owner, $guestB, Share::RESOURCE_FOLDER, $folderB->getId(), Share::PERMISSION_READ);
        $this->em->persist($shareA);
        $this->em->persist($shareB);
        $this->em->flush();

        $this->loginAs('guest-a-isolation@example.com');

        $crawler = $this->client->request('GET', '/mes-partages');
        $this->assertResponseIsSuccessful();

        $text = $crawler->filter('body')->text();
        $this->assertStringContainsString('Dossier de A', $text);
        $this->assertStringNotContainsString('Dossier de B', $text);
    }

    public function testFullAccountSeesEmptyListOnMyReceivedSharesPage(): void
    {
        $this->createWebUser('full-account@example.com', 'secret123', 'Full Account');
        $this->loginAs('full-account@example.com');

        $crawler = $this->client->request('GET', '/mes-partages');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="my-shares-empty"]');
    }

    public function testMyReceivedSharesLinkPointsToResource(): void
    {
        $owner = $this->createWebUser('owner-link@example.com', 'secret123', 'Owner Link');
        $guest = $this->createGuestUser('guest-link@example.com');
        $folder = $this->createFolder($owner, 'Dossier cible');
        $share = new Share($owner, $guest, Share::RESOURCE_FOLDER, $folder->getId(), Share::PERMISSION_READ);
        $this->em->persist($share);
        $this->em->flush();

        $this->loginAs('guest-link@example.com');

        $crawler = $this->client->request('GET', '/mes-partages');
        $this->assertResponseIsSuccessful();

        $link = $crawler->filter('[data-testid="share-row-incoming"] a')->first();
        $this->assertStringContainsString('/explorer?folder=' . $folder->getId()->toRfc4122(), $link->attr('href'));
    }

    public function testNavShowsMyReceivedSharesLinkForGuestAccount(): void
    {
        $this->createGuestUser('guest-nav@example.com');
        $this->loginAs('guest-nav@example.com');

        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('a[href="/mes-partages"]');
    }

    public function testNavHidesMyReceivedSharesLinkForFullAccount(): void
    {
        $this->createWebUser('full-nav@example.com', 'secret123', 'Full Nav');
        $this->loginAs('full-nav@example.com');

        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('a[href="/mes-partages"]');
    }
}
