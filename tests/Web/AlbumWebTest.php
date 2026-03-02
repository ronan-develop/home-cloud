<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Album;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels de la gestion des albums web (Phase 7E).
 * Couvre : liste, création, détail, ajout/suppression de médias.
 */
final class AlbumWebTest extends WebTestCase
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
        $conn->executeStatement('DELETE FROM album_media');
        $conn->executeStatement('DELETE FROM albums');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createUser(string $email = 'albums@example.com'): User
    {
        return $this->createWebUser($email, 'secret123', 'Albums User');
    }

    private function login(string $email = 'albums@example.com'): void
    {
        $this->loginAs($email);
    }

    private function createAlbum(User $user, string $name = 'Mon Album'): Album
    {
        $album = new Album($name, $user);
        $this->em->persist($album);
        $this->em->flush();

        return $album;
    }

    private function createMedia(User $user, string $name = 'photo.jpg'): \App\Entity\Media
    {
        return $this->createMediaFile($user, $name, 'photo');
    }

    // --- Accès ---

    public function testAlbumsPageRequiresLogin(): void
    {
        $this->client->request('GET', '/albums');
        $this->assertResponseRedirects('/login');
    }

    public function testAlbumsPageReturns200WhenAuthenticated(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('GET', '/albums');
        $this->assertResponseIsSuccessful();
    }

    // --- Liste ---

    public function testAlbumsListShowsUserAlbums(): void
    {
        $user = $this->createUser();
        $this->createAlbum($user, 'Vacances 2024');
        $this->createAlbum($user, 'Famille');
        $this->login();

        $this->client->request('GET', '/albums');
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Vacances 2024', $content);
        $this->assertStringContainsString('Famille', $content);
    }

    public function testAlbumsListShowsEmptyStateWhenNoAlbum(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('GET', '/albums');
        $this->assertSelectorExists('[data-testid="albums-empty"]');
    }

    public function testAlbumsListOnlyShowsOwnAlbums(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $this->createAlbum($alice, 'Album Alice');
        $this->createAlbum($bob, 'Album Bob');

        $this->login('alice@example.com');
        $this->client->request('GET', '/albums');
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Album Alice', $content);
        $this->assertStringNotContainsString('Album Bob', $content);
    }

    // --- Création ---

    public function testCreateAlbumRedirectsToAlbumDetail(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('POST', '/albums/create', ['name' => 'Nouvel Album']);
        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/albums/', $response->headers->get('Location'));
    }

    public function testCreateAlbumWithEmptyNameReturns400(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('POST', '/albums/create', ['name' => '']);
        $this->assertResponseStatusCodeSame(400);
    }

    // --- Détail ---

    public function testAlbumDetailPageReturns200(): void
    {
        $user = $this->createUser();
        $album = $this->createAlbum($user, 'Mon Super Album');
        $this->login();

        $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Mon Super Album', $this->client->getResponse()->getContent());
    }

    public function testAlbumDetailShowsMediaCount(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Album Comptage');
        $media = $this->createMedia($user, 'pic.jpg');
        $album->addMedia($media);
        $this->em->flush();
        $this->login();

        $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());
        $this->assertSelectorTextContains('[data-testid="album-media-count"]', '1');
    }

    public function testAlbumDetailForbiddenForOtherUser(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $album = $this->createAlbum($alice, 'Album Privé');

        $this->login('bob@example.com');
        $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());
        $this->assertResponseStatusCodeSame(403);
    }

    // --- Suppression ---

    public function testDeleteAlbumRedirectsToList(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'À Supprimer');
        $this->login();

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/delete');
        $this->assertResponseRedirects('/albums');
    }

    public function testDeleteAlbumForbiddenForOtherUser(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $album = $this->createAlbum($alice, 'Album Alice');

        $this->login('bob@example.com');
        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/delete');
        $this->assertResponseStatusCodeSame(403);
    }
}
