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
}
