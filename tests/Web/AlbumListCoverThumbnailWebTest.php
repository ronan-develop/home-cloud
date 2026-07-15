<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Album;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels — vignette de couverture sur les cartes de /albums.
 * TDD RED → GREEN.
 */
final class AlbumListCoverThumbnailWebTest extends WebTestCase
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

    private function createUser(string $email = 'cover-list@example.com'): User
    {
        return $this->createWebUser($email, 'secret123', 'Cover List User');
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

    public function testAlbumCardShowsCoverThumbnailWhenAlbumHasMediaWithThumbnail(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $media = $this->createMedia($user, 'photo.jpg');
        $media->setThumbnailPath('thumbs/photo.jpg');
        $album->addMedia($media);
        $this->em->flush();
        $this->loginAs('cover-list@example.com');

        $crawler = $this->client->request('GET', '/albums');

        $this->assertResponseIsSuccessful();
        $img = $crawler->filter('[data-testid="album-card-thumbnail"]');
        $this->assertGreaterThan(0, $img->count());
        $this->assertStringContainsString((string) $media->getId(), $img->attr('src'));
    }

    public function testAlbumCardShowsGenericIconWhenAlbumHasNoMediaWithThumbnail(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $media = $this->createMedia($user, 'photo.jpg');
        $media->setThumbnailPath(null);
        $album->addMedia($media);
        $this->em->flush();
        $this->loginAs('cover-list@example.com');

        $crawler = $this->client->request('GET', '/albums');

        $this->assertResponseIsSuccessful();
        $this->assertSame(0, $crawler->filter('[data-testid="album-card-thumbnail"]')->count());
    }

    public function testAlbumCardShowsExplicitCoverOverFirstMediaWithThumbnail(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $m1 = $this->createMedia($user, 'a.jpg');
        $m1->setThumbnailPath('thumbs/a.jpg');
        $m2 = $this->createMedia($user, 'b.jpg');
        $m2->setThumbnailPath('thumbs/b.jpg');
        $album->addMedia($m1);
        $album->addMedia($m2);
        $album->setCoverMedia($m2);
        $this->em->flush();
        $this->loginAs('cover-list@example.com');

        $crawler = $this->client->request('GET', '/albums');

        $img = $crawler->filter('[data-testid="album-card-thumbnail"]');
        $this->assertStringContainsString((string) $m2->getId(), $img->attr('src'));
    }
}
