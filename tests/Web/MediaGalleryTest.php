<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels de la galerie médias (Phase 7D).
 * Couvre : affichage grille thumbnails, lightbox, filtrage photo/vidéo.
 */
final class MediaGalleryTest extends WebTestCase
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

    private function createUser(string $email = 'gallery@example.com'): \App\Entity\User
    {
        return $this->createWebUser($email);
    }

    private function login(string $email = 'gallery@example.com'): void
    {
        $this->loginAs($email);
    }

    // --- Accès ---

    public function testGalleryPageRequiresLogin(): void
    {
        $this->client->request('GET', '/gallery');
        $this->assertResponseRedirects('/login');
    }

    public function testGalleryPageReturns200WhenAuthenticated(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('GET', '/gallery');
        $this->assertResponseIsSuccessful();
    }

    // --- Affichage grille ---

    public function testGalleryShowsMediaGrid(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'photo.jpg', 'photo');
        $this->login();

        $this->client->request('GET', '/gallery');
        $this->assertSelectorExists('[data-testid="media-gallery"]');
    }

    public function testGalleryShowsThumbnailWhenMediaExists(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'sunset.jpg', 'photo');
        $this->login();

        $this->client->request('GET', '/gallery');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="media-thumbnail"]');
        $this->assertSelectorTextContains('[data-testid="media-thumbnail"]', 'sunset.jpg');
    }

    public function testGalleryShowsEmptyStateWhenNoMedia(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('GET', '/gallery');
        $this->assertSelectorExists('[data-testid="gallery-empty"]');
    }

    public function testGalleryOnlyShowsCurrentUserMedia(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $this->createMediaFile($alice, 'alice-photo.jpg');
        $this->createMediaFile($bob, 'bob-photo.jpg');

        $this->login('alice@example.com');
        $html = $this->client->request('GET', '/gallery');

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('alice-photo.jpg', $content);
        $this->assertStringNotContainsString('bob-photo.jpg', $content);
    }

    // --- Lightbox ---

    public function testThumbnailHasLightboxAttribute(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'landscape.jpg');
        $this->login();

        $crawler = $this->client->request('GET', '/gallery');
        $this->assertSelectorExists('[data-lightbox]');
    }

    // --- Filtrage ---

    public function testGalleryFilterByPhoto(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'photo.jpg', 'photo');
        $this->createMediaFile($user, 'video.mp4', 'video');
        $this->login();

        $this->client->request('GET', '/gallery?type=photo');
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('photo.jpg', $content);
        $this->assertStringNotContainsString('video.mp4', $content);
    }

    public function testGalleryFilterByVideo(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'photo.jpg', 'photo');
        $this->createMediaFile($user, 'clip.mp4', 'video');
        $this->login();

        $this->client->request('GET', '/gallery?type=video');
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('clip.mp4', $content);
        $this->assertStringNotContainsString('photo.jpg', $content);
    }

    // --- Vue unique /gallery/{id} ---

    public function testViewSingleMediaReturns200(): void
    {
        $user  = $this->createUser();
        $media = $this->createMediaFile($user, 'single.jpg', 'photo');
        $this->login();

        $this->client->request('GET', '/gallery/' . $media->getId()->toRfc4122());
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('single.jpg', $this->client->getResponse()->getContent());
    }

    public function testViewSingleMediaForbiddenForOtherUser(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $media = $this->createMediaFile($alice, 'private.jpg', 'photo');

        $this->login('bob@example.com');
        $this->client->request('GET', '/gallery/' . $media->getId()->toRfc4122());
        $this->assertResponseStatusCodeSame(404);
    }

    // --- Alignement design system (dashboard) ---

    public function testGalleryHasPageHeaderWithTitle(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('GET', '/gallery');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Galerie');
    }

    public function testGalleryHasNoEmoji(): void
    {
        $this->createUser();
        $this->login();

        $crawler = $this->client->request('GET', '/gallery');
        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('📷', $crawler->filter('main')->html());
    }

    public function testGalleryHasNoHardcodedLegacyColors(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'photo.jpg', 'photo');
        $this->login();

        $crawler = $this->client->request('GET', '/gallery');
        $this->assertResponseIsSuccessful();
        $main = $crawler->filter('main')->html();

        foreach (['#111827', '#3b82f6', '#e5e7eb', '#374151', '#9ca3af', '#f3f4f6'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $main,
                sprintf('La galerie ne doit plus contenir "%s" (couleur legacy hors design system)', $forbidden)
            );
        }
    }

    // --- Tri ---

    public function testGalleryDefaultSortIsMostRecentFirst(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'old.jpg', 'photo', 1024, new \DateTimeImmutable('-2 days'));
        $this->createMediaFile($user, 'new.jpg', 'photo', 1024, new \DateTimeImmutable('-1 hour'));
        $this->login();

        $this->client->request('GET', '/gallery');
        $content = $this->client->getResponse()->getContent();
        $this->assertLessThan(
            strpos($content, 'old.jpg'),
            strpos($content, 'new.jpg'),
            'Sans tri explicite, le média le plus récent doit apparaître en premier'
        );
    }

    public function testGallerySortByOldestFirst(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'old.jpg', 'photo', 1024, new \DateTimeImmutable('-2 days'));
        $this->createMediaFile($user, 'new.jpg', 'photo', 1024, new \DateTimeImmutable('-1 hour'));
        $this->login();

        $this->client->request('GET', '/gallery?order[createdAt]=asc');
        $content = $this->client->getResponse()->getContent();
        $this->assertLessThan(
            strpos($content, 'new.jpg'),
            strpos($content, 'old.jpg'),
            '?order[createdAt]=asc doit afficher le plus ancien en premier'
        );
    }

    public function testGallerySortByNameAscending(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'banane.jpg', 'photo');
        $this->createMediaFile($user, 'abricot.jpg', 'photo');
        $this->login();

        $this->client->request('GET', '/gallery?order[originalName]=asc');
        $content = $this->client->getResponse()->getContent();
        $this->assertLessThan(
            strpos($content, 'banane.jpg'),
            strpos($content, 'abricot.jpg'),
            '?order[originalName]=asc doit afficher "abricot" avant "banane"'
        );
    }

    public function testGallerySortByNameDescending(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'banane.jpg', 'photo');
        $this->createMediaFile($user, 'abricot.jpg', 'photo');
        $this->login();

        $this->client->request('GET', '/gallery?order[originalName]=desc');
        $content = $this->client->getResponse()->getContent();
        $this->assertLessThan(
            strpos($content, 'abricot.jpg'),
            strpos($content, 'banane.jpg'),
            '?order[originalName]=desc doit afficher "banane" avant "abricot"'
        );
    }

    public function testGallerySortBySizeAscending(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'gros.jpg', 'photo', 5_000_000);
        $this->createMediaFile($user, 'petit.jpg', 'photo', 1_000);
        $this->login();

        $this->client->request('GET', '/gallery?order[size]=asc');
        $content = $this->client->getResponse()->getContent();
        $this->assertLessThan(
            strpos($content, 'gros.jpg'),
            strpos($content, 'petit.jpg'),
            '?order[size]=asc doit afficher le plus petit fichier en premier'
        );
    }

    public function testGallerySortBySizeDescending(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'gros.jpg', 'photo', 5_000_000);
        $this->createMediaFile($user, 'petit.jpg', 'photo', 1_000);
        $this->login();

        $this->client->request('GET', '/gallery?order[size]=desc');
        $content = $this->client->getResponse()->getContent();
        $this->assertLessThan(
            strpos($content, 'petit.jpg'),
            strpos($content, 'gros.jpg'),
            '?order[size]=desc doit afficher le plus gros fichier en premier'
        );
    }

    public function testGalleryUnknownSortFieldIsIgnoredNotRejected(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'photo.jpg', 'photo');
        $this->login();

        $this->client->request('GET', '/gallery?order[champInconnu]=asc');
        $this->assertResponseIsSuccessful();
    }

    public function testGallerySortMenuShowsAllOptionsWithActiveMarked(): void
    {
        $this->createUser();
        $this->login();

        $crawler = $this->client->request('GET', '/gallery?order[originalName]=asc');
        $this->assertResponseIsSuccessful();

        $select = $crawler->filter('.hc-sort-select');
        $this->assertGreaterThanOrEqual(1, $select->count(), 'Le sélecteur de tri doit exister');
        $this->assertCount(6, $select->filter('option'), 'Le sélecteur de tri doit proposer 6 options');

        $selected = $select->filter('option[selected]');
        $this->assertCount(1, $selected, 'Une seule option doit être marquée comme sélectionnée');
    }

    public function testGallerySortCombinesWithTypeFilter(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'banane.jpg', 'photo');
        $this->createMediaFile($user, 'abricot.jpg', 'photo');
        $this->createMediaFile($user, 'video-z.mp4', 'video');
        $this->login();

        $this->client->request('GET', '/gallery?type=photo&order[originalName]=asc');
        $content = $this->client->getResponse()->getContent();

        $this->assertStringNotContainsString('video-z.mp4', $content);
        $this->assertLessThan(
            strpos($content, 'banane.jpg'),
            strpos($content, 'abricot.jpg'),
            'Le tri doit continuer à s\'appliquer quand il est combiné au filtre type'
        );
    }

    // --- Sélection multiple pour ajout à un album ---

    public function testGalleryWithoutAlbumParamHasNoSelectionCheckboxes(): void
    {
        $user  = $this->createUser();
        $media = $this->createMediaFile($user, 'photo.jpg', 'photo');
        $this->login();

        $this->client->request('GET', '/gallery');
        $this->assertSelectorNotExists('input[type="checkbox"][name="mediaIds[]"][value="' . $media->getId()->toRfc4122() . '"]');
    }

    public function testGalleryWithAlbumParamShowsSelectionCheckboxes(): void
    {
        $user  = $this->createUser();
        $media = $this->createMediaFile($user, 'photo.jpg', 'photo');
        $album = new \App\Entity\Album('Vacances', $user);
        $this->em->persist($album);
        $this->em->flush();
        $this->login();

        $this->client->request('GET', '/gallery?album=' . $album->getId()->toRfc4122());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[type="checkbox"][name="mediaIds[]"][value="' . $media->getId()->toRfc4122() . '"]');
    }

    public function testGalleryWithAlbumParamShowsAddToThisAlbumBar(): void
    {
        $user  = $this->createUser();
        $this->createMediaFile($user, 'photo.jpg', 'photo');
        $album = new \App\Entity\Album('Vacances', $user);
        $this->em->persist($album);
        $this->em->flush();
        $this->login();

        $this->client->request('GET', '/gallery?album=' . $album->getId()->toRfc4122());
        $this->assertSelectorExists('[data-testid="gallery-add-to-current-album"][data-album-id="' . $album->getId()->toRfc4122() . '"]');
    }

    public function testGalleryWithUnknownAlbumParamIgnoresIt(): void
    {
        $user  = $this->createUser();
        $media = $this->createMediaFile($user, 'photo.jpg', 'photo');
        $this->login();

        $this->client->request('GET', '/gallery?album=019f5700-0000-7000-8000-000000000000');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('input[type="checkbox"][name="mediaIds[]"][value="' . $media->getId()->toRfc4122() . '"]');
    }

    public function testGalleryWithAlbumParamNotOwnedByUserIsForbidden(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $album = new \App\Entity\Album('Album Alice', $alice);
        $this->em->persist($album);
        $this->em->flush();

        $this->login('bob@example.com');
        $this->client->request('GET', '/gallery?album=' . $album->getId()->toRfc4122());
        $this->assertResponseStatusCodeSame(403);
    }

}
