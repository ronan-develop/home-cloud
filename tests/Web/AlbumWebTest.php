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

    public function testCreateAlbumWithSelectedMediaIdsAddsThemToAlbum(): void
    {
        $user   = $this->createUser();
        $media1 = $this->createMedia($user, 'photo1.jpg');
        $media2 = $this->createMedia($user, 'photo2.jpg');
        $this->login();

        $this->client->request('POST', '/albums/create', [
            'name'     => 'Vacances',
            'mediaIds' => [$media1->getId()->toRfc4122(), $media2->getId()->toRfc4122()],
        ]);

        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect());

        $this->client->followRedirect();
        $this->assertSelectorTextContains('[data-testid="album-media-count"]', '2');
    }

    public function testCreateAlbumIgnoresMediaIdNotOwnedByUser(): void
    {
        $alice      = $this->createUser('alice@example.com');
        $bob        = $this->createUser('bob@example.com');
        $bobsMedia  = $this->createMedia($bob, 'secret.jpg');

        $this->login('alice@example.com');
        $this->client->request('POST', '/albums/create', [
            'name'     => 'Vacances',
            'mediaIds' => [$bobsMedia->getId()->toRfc4122()],
        ]);

        $this->client->followRedirect();
        $this->assertSelectorTextContains('[data-testid="album-media-count"]', '0');
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

    // --- Ajout de médias à un album existant ---

    public function testAddMediaToAlbumRedirectsToAlbumDetail(): void
    {
        $user   = $this->createUser();
        $album  = $this->createAlbum($user, 'Vacances');
        $media  = $this->createMedia($user, 'photo.jpg');
        $this->login();

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/add-media', [
            'mediaIds' => [$media->getId()->toRfc4122()],
        ]);

        $this->assertResponseRedirects('/albums/' . $album->getId()->toRfc4122());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('[data-testid="album-media-count"]', '1');
    }

    public function testAddMediaToAlbumIgnoresMediaNotOwnedByUser(): void
    {
        $alice     = $this->createUser('alice@example.com');
        $bob       = $this->createUser('bob@example.com');
        $album     = $this->createAlbum($alice, 'Vacances');
        $bobsMedia = $this->createMedia($bob, 'secret.jpg');

        $this->login('alice@example.com');
        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/add-media', [
            'mediaIds' => [$bobsMedia->getId()->toRfc4122()],
        ]);

        $this->client->followRedirect();
        $this->assertSelectorTextContains('[data-testid="album-media-count"]', '0');
    }

    public function testAddMediaToAlbumForbiddenForOtherUser(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $album = $this->createAlbum($alice, 'Album Alice');
        $media = $this->createMedia($bob, 'photo.jpg');

        $this->login('bob@example.com');
        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/add-media', [
            'mediaIds' => [$media->getId()->toRfc4122()],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    // --- Retrait d'un média d'un album ---

    public function testRemoveMediaFromAlbumRedirectsToAlbumDetail(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $media = $this->createMedia($user, 'photo.jpg');
        $album->addMedia($media);
        $this->em->flush();
        $this->login();

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/medias/' . $media->getId()->toRfc4122() . '/remove');

        $this->assertResponseRedirects('/albums/' . $album->getId()->toRfc4122());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('[data-testid="album-media-count"]', '0');
    }

    public function testRemoveMediaFromAlbumForbiddenForOtherUser(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $album = $this->createAlbum($alice, 'Album Alice');
        $media = $this->createMedia($alice, 'photo.jpg');
        $album->addMedia($media);
        $this->em->flush();

        $this->login('bob@example.com');
        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/medias/' . $media->getId()->toRfc4122() . '/remove');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRemoveMediaFromAlbumDoesNotDeleteTheMediaItself(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $media = $this->createMedia($user, 'photo.jpg');
        $album->addMedia($media);
        $this->em->flush();
        $this->login();

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/medias/' . $media->getId()->toRfc4122() . '/remove');

        $this->client->request('GET', '/gallery');
        $this->assertStringContainsString('photo.jpg', $this->client->getResponse()->getContent());
    }

    // --- Réordonnancement des médias d'un album ---

    public function testReorderAlbumMediaRedirectsToAlbumDetail(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $m1 = $this->createMedia($user, 'a.jpg');
        $m2 = $this->createMedia($user, 'b.jpg');
        $album->addMedia($m1);
        $album->addMedia($m2);
        $this->em->flush();
        $this->login();

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/reorder', [
            'mediaIds' => [$m2->getId()->toRfc4122(), $m1->getId()->toRfc4122()],
        ]);

        $this->assertResponseRedirects('/albums/' . $album->getId()->toRfc4122());
    }

    public function testReorderAlbumMediaPersistsNewOrder(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $m1 = $this->createMedia($user, 'a.jpg');
        $m2 = $this->createMedia($user, 'b.jpg');
        $album->addMedia($m1);
        $album->addMedia($m2);
        $this->em->flush();
        $albumId = $album->getId()->toRfc4122();
        $this->login();

        $this->client->request('POST', '/albums/' . $albumId . '/reorder', [
            'mediaIds' => [$m2->getId()->toRfc4122(), $m1->getId()->toRfc4122()],
        ]);

        $this->em->clear();
        $reloaded = $this->em->getRepository(Album::class)->find($album->getId());
        $medias = $reloaded->getMedias()->toArray();
        $this->assertSame($m2->getId()->toRfc4122(), $medias[0]->getId()->toRfc4122());
        $this->assertSame($m1->getId()->toRfc4122(), $medias[1]->getId()->toRfc4122());
    }

    public function testReorderAlbumMediaForbiddenForOtherUser(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $album = $this->createAlbum($alice, 'Album Alice');
        $media = $this->createMedia($alice, 'photo.jpg');
        $album->addMedia($media);
        $this->em->flush();

        $this->login('bob@example.com');
        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/reorder', [
            'mediaIds' => [$media->getId()->toRfc4122()],
        ]);

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

    // --- Alignement design system (dashboard) ---

    public function testAlbumsPageShowsSelectablePhotosForCreation(): void
    {
        $user  = $this->createUser();
        $media = $this->createMedia($user, 'photo.jpg');
        $this->login();

        $crawler = $this->client->request('GET', '/albums');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[type="checkbox"][name="mediaIds[]"][value="' . $media->getId()->toRfc4122() . '"]');
    }

    public function testAlbumsListHasPageHeaderWithTitle(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('GET', '/albums');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mes albums');
    }

    public function testAlbumsListHasNoHardcodedLegacyColors(): void
    {
        $user = $this->createUser();
        $this->createAlbum($user, 'Vacances 2024');
        $this->login();

        $crawler = $this->client->request('GET', '/albums');
        $this->assertResponseIsSuccessful();
        $main = $crawler->filter('main')->html();

        foreach (['#111827', '#3b82f6', '#e5e7eb', '#d1d5db', '#9ca3af', '#ef4444'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $main,
                sprintf('La liste des albums ne doit plus contenir "%s" (couleur legacy hors design system)', $forbidden)
            );
        }
    }

    public function testAlbumDetailHasNoHardcodedLegacyColors(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Album Détail');
        $media = $this->createMedia($user, 'pic.jpg');
        $album->addMedia($media);
        $this->em->flush();
        $this->login();

        $crawler = $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());
        $this->assertResponseIsSuccessful();
        $main = $crawler->filter('main')->html();

        foreach (['#111827', '#3b82f6', '#e5e7eb', '#d1d5db', '#9ca3af', '#ef4444', '#6b7280', '#f3f4f6'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $main,
                sprintf('Le détail d\'album ne doit plus contenir "%s" (couleur legacy hors design system)', $forbidden)
            );
        }
    }
}
