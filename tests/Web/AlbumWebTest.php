<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Album;
use App\Entity\Share;
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

    /**
     * Récupère un token CSRF valide en le lisant depuis une page réellement
     * rendue par le client, comme le ferait un navigateur.
     */
    private function csrfTokenFrom(string $url, string $selector): string
    {
        $crawler = $this->client->request('GET', $url);

        return $crawler->filter($selector)->first()->attr('value');
    }

    private function albumCreateToken(): string
    {
        return $this->csrfTokenFrom('/albums', '#new-album-modal input[name="_token"]');
    }

    private function albumAddMediaToken(string $albumId): string
    {
        return $this->csrfTokenFrom('/gallery?album=' . $albumId, '#gallery-add-to-album-form input[name="_token"]');
    }

    private function albumRemoveMediaToken(string $albumId): string
    {
        return $this->csrfTokenFrom('/albums/' . $albumId, '.hc-media-thumb-action-form input[name="_token"]');
    }

    private function albumReorderToken(string $albumId): string
    {
        $crawler = $this->client->request('GET', '/albums/' . $albumId);

        return $crawler->filter('[data-controller="album-reorder"]')->attr('data-album-reorder-csrf-token-value');
    }

    private function albumImportToken(string $albumId): string
    {
        return $this->csrfTokenFrom('/albums/' . $albumId, 'form[action*="import"] input[name="_token"]');
    }

    private function albumDeleteToken(string $albumId): string
    {
        return $this->csrfTokenFrom('/albums/' . $albumId, 'form[action*="delete"] input[name="_token"]');
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
        $token = $this->albumCreateToken();

        $this->client->request('POST', '/albums/create', ['name' => 'Nouvel Album', '_token' => $token]);
        $response = $this->client->getResponse();
        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/albums/', $response->headers->get('Location'));
    }

    public function testCreateAlbumWithoutCsrfTokenThrows403(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('POST', '/albums/create', ['name' => 'Nouvel Album']);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateAlbumWithEmptyNameReturns400(): void
    {
        $this->createUser();
        $this->login();
        $token = $this->albumCreateToken();

        $this->client->request('POST', '/albums/create', ['name' => '', '_token' => $token]);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testCreateAlbumWithSelectedMediaIdsAddsThemToAlbum(): void
    {
        $user   = $this->createUser();
        $media1 = $this->createMedia($user, 'photo1.jpg');
        $media2 = $this->createMedia($user, 'photo2.jpg');
        $this->login();
        $token = $this->albumCreateToken();

        $this->client->request('POST', '/albums/create', [
            'name'     => 'Vacances',
            'mediaIds' => [$media1->getId()->toRfc4122(), $media2->getId()->toRfc4122()],
            '_token'   => $token,
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
        $token = $this->albumCreateToken();
        $this->client->request('POST', '/albums/create', [
            'name'     => 'Vacances',
            'mediaIds' => [$bobsMedia->getId()->toRfc4122()],
            '_token'   => $token,
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
        $token = $this->albumAddMediaToken($album->getId()->toRfc4122());

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/add-media', [
            'mediaIds' => [$media->getId()->toRfc4122()],
            '_token'   => $token,
        ]);

        $this->assertResponseRedirects('/albums/' . $album->getId()->toRfc4122());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('[data-testid="album-media-count"]', '1');
    }

    public function testAddMediaToAlbumWithoutCsrfTokenThrows403(): void
    {
        $user   = $this->createUser();
        $album  = $this->createAlbum($user, 'Vacances');
        $media  = $this->createMedia($user, 'photo.jpg');
        $this->login();

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/add-media', [
            'mediaIds' => [$media->getId()->toRfc4122()],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testAddMediaToAlbumIgnoresMediaNotOwnedByUser(): void
    {
        $alice     = $this->createUser('alice@example.com');
        $bob       = $this->createUser('bob@example.com');
        $album     = $this->createAlbum($alice, 'Vacances');
        $bobsMedia = $this->createMedia($bob, 'secret.jpg');
        // Alice a besoin d'un média à elle pour que /gallery?album= rende le
        // formulaire porteur du token CSRF.
        $this->createMedia($alice, 'own.jpg');

        $this->login('alice@example.com');
        $token = $this->albumAddMediaToken($album->getId()->toRfc4122());
        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/add-media', [
            'mediaIds' => [$bobsMedia->getId()->toRfc4122()],
            '_token'   => $token,
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
        // Bob a besoin d'un album à lui pour que /gallery?album= rende le
        // formulaire porteur du token CSRF (il n'a pas accès à celui d'Alice).
        $bobAlbum = $this->createAlbum($bob, 'Album Bob');

        $this->login('bob@example.com');
        $token = $this->albumAddMediaToken($bobAlbum->getId()->toRfc4122());
        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/add-media', [
            'mediaIds' => [$media->getId()->toRfc4122()],
            '_token'   => $token,
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
        $token = $this->albumRemoveMediaToken($album->getId()->toRfc4122());

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/medias/' . $media->getId()->toRfc4122() . '/remove', ['_token' => $token]);

        $this->assertResponseRedirects('/albums/' . $album->getId()->toRfc4122());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('[data-testid="album-media-count"]', '0');
    }

    public function testRemoveMediaFromAlbumWithoutCsrfTokenThrows403(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $media = $this->createMedia($user, 'photo.jpg');
        $album->addMedia($media);
        $this->em->flush();
        $this->login();

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/medias/' . $media->getId()->toRfc4122() . '/remove');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRemoveMediaFromAlbumForbiddenForOtherUser(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $album = $this->createAlbum($alice, 'Album Alice');
        $media = $this->createMedia($alice, 'photo.jpg');
        $album->addMedia($media);
        $this->em->flush();
        // Bob a besoin d'un album à lui pour que /albums/{id} rende le
        // formulaire porteur du token CSRF "album-remove-media".
        $bobAlbum = $this->createAlbum($bob, 'Album Bob');
        $bobMedia = $this->createMedia($bob, 'bob.jpg');
        $bobAlbum->addMedia($bobMedia);
        $this->em->flush();

        $this->login('bob@example.com');
        $token = $this->albumRemoveMediaToken($bobAlbum->getId()->toRfc4122());
        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/medias/' . $media->getId()->toRfc4122() . '/remove', ['_token' => $token]);

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
        $token = $this->albumRemoveMediaToken($album->getId()->toRfc4122());

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/medias/' . $media->getId()->toRfc4122() . '/remove', ['_token' => $token]);

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
        $token = $this->albumReorderToken($album->getId()->toRfc4122());

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/reorder', [
            'mediaIds' => [$m2->getId()->toRfc4122(), $m1->getId()->toRfc4122()],
            '_token'   => $token,
        ]);

        $this->assertResponseRedirects('/albums/' . $album->getId()->toRfc4122());
    }

    public function testReorderAlbumMediaWithoutCsrfTokenThrows403(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $m1 = $this->createMedia($user, 'a.jpg');
        $album->addMedia($m1);
        $this->em->flush();
        $this->login();

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/reorder', [
            'mediaIds' => [$m1->getId()->toRfc4122()],
        ]);

        $this->assertResponseStatusCodeSame(403);
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
        $token = $this->albumReorderToken($albumId);

        $this->client->request('POST', '/albums/' . $albumId . '/reorder', [
            'mediaIds' => [$m2->getId()->toRfc4122(), $m1->getId()->toRfc4122()],
            '_token'   => $token,
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
        // Bob a besoin d'un album à lui, avec un média, pour que
        // /albums/{id} rende le formulaire porteur du token CSRF "album-reorder".
        $bobAlbum = $this->createAlbum($bob, 'Album Bob');
        $bobMedia = $this->createMedia($bob, 'bob.jpg');
        $bobAlbum->addMedia($bobMedia);
        $this->em->flush();

        $this->login('bob@example.com');
        $token = $this->albumReorderToken($bobAlbum->getId()->toRfc4122());
        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/reorder', [
            'mediaIds' => [$media->getId()->toRfc4122()],
            '_token'   => $token,
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    // --- Import direct depuis le disque ---

    /**
     * Copie temporaire de la fixture — UploadedFile en mode "test" (dernier
     * argument true) ne déplace pas le fichier physiquement, mais on copie
     * quand même par prudence pour ne jamais risquer d'altérer la fixture
     * source versionnée dans le repo.
     */
    private function sampleUploadedPhoto(string $clientFilename = 'sunset.jpg'): \Symfony\Component\HttpFoundation\File\UploadedFile
    {
        $source = dirname(__DIR__, 2) . '/fixtures/demo-photos/kanenori-sunset-7133867_1920.jpg';
        $tmp    = tempnam(sys_get_temp_dir(), 'album_import_test_') . '.jpg';
        copy($source, $tmp);

        return new \Symfony\Component\HttpFoundation\File\UploadedFile($tmp, $clientFilename, 'image/jpeg', null, true);
    }

    public function testImportPhotoToAlbumCreatesMediaAndAddsItImmediately(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $this->login();
        $token = $this->albumImportToken($album->getId()->toRfc4122());

        $uploadedFile = $this->sampleUploadedPhoto();

        $this->client->request(
            'POST',
            '/albums/' . $album->getId()->toRfc4122() . '/import',
            ['_token' => $token],
            ['files' => [$uploadedFile]],
        );

        $this->assertResponseRedirects('/albums/' . $album->getId()->toRfc4122());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('[data-testid="album-media-count"]', '1');
    }

    public function testImportPhotoToAlbumWithoutCsrfTokenThrows403(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $this->login();

        $uploadedFile = $this->sampleUploadedPhoto();

        $this->client->request(
            'POST',
            '/albums/' . $album->getId()->toRfc4122() . '/import',
            [],
            ['files' => [$uploadedFile]],
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testImportPhotoToAlbumForbiddenForOtherUser(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $album = $this->createAlbum($alice, 'Album Alice');
        // Bob a besoin d'un album à lui pour que /albums/{id} rende le
        // formulaire porteur du token CSRF "album-import".
        $bobAlbum = $this->createAlbum($bob, 'Album Bob');

        $this->login('bob@example.com');
        $token = $this->albumImportToken($bobAlbum->getId()->toRfc4122());

        $uploadedFile = $this->sampleUploadedPhoto();

        $this->client->request(
            'POST',
            '/albums/' . $album->getId()->toRfc4122() . '/import',
            ['_token' => $token],
            ['files' => [$uploadedFile]],
        );

        $this->assertResponseStatusCodeSame(403);
    }

    // --- Suppression ---

    public function testDeleteAlbumRedirectsToList(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'À Supprimer');
        $this->login();
        $token = $this->albumDeleteToken($album->getId()->toRfc4122());

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/delete', ['_token' => $token]);
        $this->assertResponseRedirects('/albums');
    }

    public function testDeleteAlbumWithoutCsrfTokenThrows403(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'À Supprimer');
        $this->login();

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/delete');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteAlbumForbiddenForOtherUser(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $album = $this->createAlbum($alice, 'Album Alice');
        // Bob a besoin d'un album à lui pour que /albums/{id} rende le
        // formulaire porteur du token CSRF "album-delete".
        $bobAlbum = $this->createAlbum($bob, 'Album Bob');

        $this->login('bob@example.com');
        $token = $this->albumDeleteToken($bobAlbum->getId()->toRfc4122());
        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/delete', ['_token' => $token]);
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

    // --- Diaporama ---

    public function testAlbumDetailThumbnailsAreLightboxLinks(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $media = $this->createMedia($user, 'photo.jpg');
        $album->addMedia($media);
        $this->em->flush();
        $this->login();

        $crawler = $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-lightbox][data-full-src]');
    }

    public function testAlbumDetailVideoThumbnailHasMediaTypeAttribute(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $media = $this->createMediaFile($user, 'clip.mp4', 'video');
        $album->addMedia($media);
        $this->em->flush();
        $this->login();

        $crawler = $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-lightbox][data-media-type="video"]');
    }

    public function testAlbumDetailHasSlideshowToggle(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $media = $this->createMedia($user, 'photo.jpg');
        $album->addMedia($media);
        $this->em->flush();
        $this->login();

        $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());
        $this->assertSelectorExists('[data-testid="lightbox-slideshow-toggle"]');
    }

    // --- Partage ---

    public function testGuestWithActiveShareCanViewAlbumDetail(): void
    {
        $owner = $this->createUser('owner@example.com');
        $guest = $this->createUser('guest@example.com');
        $album = $this->createAlbum($owner, 'Vacances partagées');
        $share = new Share($owner, $guest, 'album', $album->getId(), 'read');
        $this->em->persist($share);
        $this->em->flush();

        $this->login('guest@example.com');

        $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());
        $this->assertResponseIsSuccessful();
    }

    public function testGuestWithoutShareCannotViewAlbumDetail(): void
    {
        $owner = $this->createUser('owner@example.com');
        $this->createUser('guest@example.com');
        $album = $this->createAlbum($owner, 'Album privé');
        $this->em->flush();

        $this->login('guest@example.com');

        $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());
        $this->assertResponseStatusCodeSame(403);
    }

    public function testGuestWithExpiredShareCannotViewAlbumDetail(): void
    {
        $owner = $this->createUser('owner@example.com');
        $guest = $this->createUser('guest@example.com');
        $album = $this->createAlbum($owner, 'Album expiré');
        $share = new Share($owner, $guest, 'album', $album->getId(), 'read', new \DateTimeImmutable('-1 day'));
        $this->em->persist($share);
        $this->em->flush();

        $this->login('guest@example.com');

        $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());
        $this->assertResponseStatusCodeSame(403);
    }

    public function testGuestWithWriteShareCannotDeleteAlbum(): void
    {
        $owner = $this->createUser('owner@example.com');
        $guest = $this->createUser('guest@example.com');
        $album = $this->createAlbum($owner, 'Album protégé');
        $share = new Share($owner, $guest, 'album', $album->getId(), 'write');
        $this->em->persist($share);
        $this->em->flush();

        $this->login('guest@example.com');
        $token = $this->csrfTokenFrom('/albums/' . $album->getId()->toRfc4122(), 'form[action*="/delete"] input[name="_token"]');

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/delete', ['_token' => $token]);
        $this->assertResponseStatusCodeSame(403);
    }
}
