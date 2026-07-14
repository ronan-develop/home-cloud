<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShareWebTest extends WebTestCase
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

    private function createUser(string $email = 'shares@example.com'): User
    {
        return $this->createWebUser($email, 'secret123', 'Shares User');
    }

    private function login(string $email = 'shares@example.com'): void
    {
        $this->loginAs($email);
    }

    private function createFile(User $owner, string $name = 'photo.jpg'): File
    {
        $folder = new Folder('Docs', $owner);
        $this->em->persist($folder);
        $file = new File($name, 'image/jpeg', 1024, "test/{$name}", $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    public function testSharesPageRequiresLogin(): void
    {
        $this->client->request('GET', '/partages');

        $this->assertResponseRedirects('/login');
    }

    public function testSharesPageListsOutgoingShares(): void
    {
        $owner = $this->createUser();
        $guest = $this->createUser('guest-out@example.com');
        $file  = $this->createFile($owner, 'partage-sortant.jpg');
        $share = new Share($owner, $guest, Share::RESOURCE_FILE, $file->getId(), Share::PERMISSION_READ);
        $this->em->persist($share);
        $this->em->flush();

        $this->login();

        $crawler = $this->client->request('GET', '/partages');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('partage-sortant.jpg', $crawler->filter('body')->text());
    }

    public function testSharesPageListsOutgoingFolderShareWithNameGuestAndPermission(): void
    {
        $owner  = $this->createUser();
        $guest  = $this->createUser('guest-folder@example.com');
        $folder = new Folder('Documents partagés', $owner);
        $this->em->persist($folder);
        $this->em->flush();

        $share = new Share($owner, $guest, Share::RESOURCE_FOLDER, $folder->getId(), Share::PERMISSION_WRITE);
        $this->em->persist($share);
        $this->em->flush();

        $this->login();

        $crawler = $this->client->request('GET', '/partages');
        $this->assertResponseIsSuccessful();

        $row = $crawler->filter('[data-testid="share-row-outgoing"]')->text();
        $this->assertStringContainsString('Documents partagés', $row);
        $this->assertStringContainsString($guest->getDisplayName(), $row);
        $this->assertStringContainsString('lecture/écriture', $row);
        $this->assertStringContainsString('permanent', $row);
    }

    public function testSharesPageShowsExpirationDateWhenSet(): void
    {
        $owner  = $this->createUser();
        $guest  = $this->createUser('guest-expiry@example.com');
        $folder = new Folder('Dossier temporaire', $owner);
        $this->em->persist($folder);
        $this->em->flush();

        $expiresAt = new \DateTimeImmutable('+7 days');
        $share = new Share($owner, $guest, Share::RESOURCE_FOLDER, $folder->getId(), Share::PERMISSION_READ, $expiresAt);
        $this->em->persist($share);
        $this->em->flush();

        $this->login();

        $crawler = $this->client->request('GET', '/partages');
        $this->assertResponseIsSuccessful();

        $row = $crawler->filter('[data-testid="share-row-outgoing"]')->text();
        $this->assertStringContainsString($expiresAt->format('d/m/Y'), $row);
    }

    public function testSharesPageListsIncomingShares(): void
    {
        $owner = $this->createUser('owner-in@example.com');
        $guest = $this->createUser(); // shares@example.com, celui qui se connecte
        $file  = $this->createFile($owner, 'partage-entrant.jpg');
        $share = new Share($owner, $guest, Share::RESOURCE_FILE, $file->getId(), Share::PERMISSION_READ);
        $this->em->persist($share);
        $this->em->flush();

        $this->login();

        $crawler = $this->client->request('GET', '/partages');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('partage-entrant.jpg', $crawler->filter('body')->text());
    }

    public function testSidebarLinkPointsToSharesPage(): void
    {
        $this->createUser();
        $this->login();

        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $link = $crawler->filter('a:contains("Partages")');
        $this->assertGreaterThan(0, $link->count());
        $this->assertSame('/partages', $link->attr('href'));
    }

    // ─── Création d'un partage depuis un album (formulaire ShareModal) ──────

    private function createAlbum(User $owner, string $name = 'Vacances'): Album
    {
        $album = new Album($name, $owner);
        $this->em->persist($album);
        $this->em->flush();

        return $album;
    }

    private function shareCreateToken(string $resourceId): string
    {
        $crawler = $this->client->request('GET', '/albums/' . $resourceId);

        return $crawler->filter('form[action*="/share-create"] input[name="_token"]')->attr('value');
    }

    public function testCreateShareFromAlbumRedirectsWithSuccessFlash(): void
    {
        $owner = $this->createUser();
        $this->createUser('invite@example.com');
        $album = $this->createAlbum($owner);

        $this->login();
        $token = $this->shareCreateToken($album->getId()->toRfc4122());

        $this->client->request('POST', '/share-create', [
            '_token'       => $token,
            'guestEmail'   => 'invite@example.com',
            'resourceType' => 'album',
            'resourceId'   => $album->getId()->toRfc4122(),
            'permission'   => 'read',
        ]);

        $this->assertResponseRedirects('/albums/' . $album->getId()->toRfc4122());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', 'partagé');
    }

    public function testCreateShareWithUnknownEmailShowsErrorFlash(): void
    {
        $owner = $this->createUser();
        $album = $this->createAlbum($owner);

        $this->login();
        $token = $this->shareCreateToken($album->getId()->toRfc4122());

        $this->client->request('POST', '/share-create', [
            '_token'       => $token,
            'guestEmail'   => 'inconnu@example.com',
            'resourceType' => 'album',
            'resourceId'   => $album->getId()->toRfc4122(),
            'permission'   => 'read',
        ]);

        $this->assertResponseRedirects('/albums/' . $album->getId()->toRfc4122());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-error', 'Aucun compte HomeCloud');
    }

    public function testCreateShareWithoutCsrfTokenReturns403(): void
    {
        $owner = $this->createUser();
        $this->createUser('invite2@example.com');
        $album = $this->createAlbum($owner);

        $this->login();

        $this->client->request('POST', '/share-create', [
            'guestEmail'   => 'invite2@example.com',
            'resourceType' => 'album',
            'resourceId'   => $album->getId()->toRfc4122(),
            'permission'   => 'read',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAlbumDetailHasShareButtonAndModal(): void
    {
        $owner = $this->createUser();
        $album = $this->createAlbum($owner);

        $this->login();

        $crawler = $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="share-open-btn"]');
        $this->assertSelectorExists('form[action*="/share-create"]');
    }
}
