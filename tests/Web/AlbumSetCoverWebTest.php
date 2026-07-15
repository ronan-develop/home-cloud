<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Album;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels — choix explicite de la couverture d'un album.
 * TDD RED → GREEN.
 */
final class AlbumSetCoverWebTest extends WebTestCase
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

    private function createUser(string $email = 'cover@example.com'): User
    {
        return $this->createWebUser($email, 'secret123', 'Cover User');
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

    private function setCoverToken(string $albumId): string
    {
        $crawler = $this->client->request('GET', '/albums/' . $albumId);

        return $crawler->filter('form[action*="/set-cover"] input[name="_token"]')->first()->attr('value');
    }

    public function testSetCoverRedirectsToAlbumDetail(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $media = $this->createMedia($user, 'photo.jpg');
        $album->addMedia($media);
        $this->em->flush();
        $this->loginAs('cover@example.com');
        $token = $this->setCoverToken($album->getId()->toRfc4122());

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/set-cover', [
            '_token'  => $token,
            'mediaId' => $media->getId()->toRfc4122(),
        ]);

        $this->assertResponseRedirects('/albums/' . $album->getId()->toRfc4122());
    }

    public function testSetCoverMarksSelectedMediaAsCoverOnNextPageLoad(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $media = $this->createMedia($user, 'photo.jpg');
        $album->addMedia($media);
        $this->em->flush();
        $this->loginAs('cover@example.com');
        $token = $this->setCoverToken($album->getId()->toRfc4122());

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/set-cover', [
            '_token'  => $token,
            'mediaId' => $media->getId()->toRfc4122(),
        ]);

        $crawler = $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());
        $this->assertSelectorExists('[data-testid="album-cover-badge"][data-media-id="' . $media->getId()->toRfc4122() . '"]');
    }

    public function testSetCoverWithoutCsrfTokenThrows403(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $media = $this->createMedia($user, 'photo.jpg');
        $album->addMedia($media);
        $this->em->flush();
        $this->loginAs('cover@example.com');

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/set-cover', [
            'mediaId' => $media->getId()->toRfc4122(),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testSetCoverForbiddenForOtherUser(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $album = $this->createAlbum($alice, 'Album Alice');
        $media = $this->createMedia($alice, 'photo.jpg');
        $album->addMedia($media);
        $this->em->flush();
        $bobAlbum = $this->createAlbum($bob, 'Album Bob');
        $bobMedia = $this->createMedia($bob, 'bob.jpg');
        $bobAlbum->addMedia($bobMedia);
        $this->em->flush();

        $this->loginAs('bob@example.com');
        $token = $this->setCoverToken($bobAlbum->getId()->toRfc4122());

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/set-cover', [
            '_token'  => $token,
            'mediaId' => $media->getId()->toRfc4122(),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testSetCoverWithMediaNotInAlbumReturns400(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $inAlbumMedia = $this->createMedia($user, 'in-album.jpg');
        $album->addMedia($inAlbumMedia);
        $foreignMedia = $this->createMedia($user, 'foreign.jpg');
        $this->em->flush();
        $this->loginAs('cover@example.com');
        $token = $this->setCoverToken($album->getId()->toRfc4122());

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/set-cover', [
            '_token'  => $token,
            'mediaId' => $foreignMedia->getId()->toRfc4122(),
        ]);

        $this->assertResponseStatusCodeSame(400);
    }
}
