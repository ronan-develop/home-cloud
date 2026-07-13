<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Album;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels du badge d'appartenance à un album sur les vignettes
 * de la galerie (/gallery) : affiche le nom du 1er album + un compteur si
 * le média appartient à plusieurs albums, rien si aucun.
 */
final class MediaGalleryAlbumBadgeTest extends WebTestCase
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

    public function testMediaWithoutAlbumShowsNoBadge(): void
    {
        $user = $this->createUser();
        $this->createMediaFile($user, 'sans-album.jpg', 'photo');
        $this->login();

        $crawler = $this->client->request('GET', '/gallery');

        $this->assertCount(0, $crawler->filter('[data-testid="media-album-badge"]'));
    }

    public function testMediaInOneAlbumShowsAlbumName(): void
    {
        $user  = $this->createUser();
        $media = $this->createMediaFile($user, 'photo.jpg', 'photo');
        $album = new Album('Vacances', $user);
        $album->addMedia($media);
        $this->em->persist($album);
        $this->em->flush();
        $this->login();

        $crawler = $this->client->request('GET', '/gallery');

        $badge = $crawler->filter('[data-testid="media-album-badge"]');
        $this->assertCount(1, $badge);
        $this->assertStringContainsString('Vacances', $badge->text());
    }

    public function testMediaInMultipleAlbumsShowsFirstNameAndCount(): void
    {
        $user  = $this->createUser();
        $media = $this->createMediaFile($user, 'photo.jpg', 'photo');

        $albumA = new Album('Vacances', $user);
        $albumA->addMedia($media);
        $this->em->persist($albumA);

        $albumB = new Album('Noël', $user);
        $albumB->addMedia($media);
        $this->em->persist($albumB);

        $albumC = new Album('Anniversaire', $user);
        $albumC->addMedia($media);
        $this->em->persist($albumC);

        $this->em->flush();
        $this->login();

        $crawler = $this->client->request('GET', '/gallery');

        $badge = $crawler->filter('[data-testid="media-album-badge"]');
        $this->assertCount(1, $badge);
        $this->assertStringContainsString('Vacances', $badge->text());
        $this->assertStringContainsString('+2', $badge->text());
    }
}
