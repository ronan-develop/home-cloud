<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Media;
use App\Entity\Share;
use App\Entity\ShareLink;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La page publique d'un album partagé doit afficher une grille de vignettes
 * et permettre d'ouvrir la visionneuse (lightbox), pas seulement un résumé
 * textuel ("3 médias") — signalé en usage réel : le contenu était invisible.
 * Sans sidebar, sans icônes d'action (visiteur anonyme, lecture seule).
 */
final class PublicShareAlbumViewerWebTest extends WebTestCase
{
    use WebFixturesTrait;

    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private string $storageDir;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->storageDir = static::getContainer()->getParameter('app.storage_dir');
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM share_links');
        $conn->executeStatement('DELETE FROM shares');
        $conn->executeStatement('DELETE FROM album_media');
        $conn->executeStatement('DELETE FROM albums');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createOwner(): User
    {
        return $this->createWebUser('public-album-owner@example.com', 'secret123', 'Owner');
    }

    private function createAlbumWithMedia(User $owner): array
    {
        $album = new Album('Vacances', $owner);
        $album->setVisibility(Album::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($album);

        $folder = new Folder('Photos', $owner);
        $this->em->persist($folder);

        $rel = 'public-album/' . uniqid() . '.jpg';
        @mkdir($this->storageDir . '/public-album', 0777, true);
        file_put_contents($this->storageDir . '/' . $rel, 'contenu de test');

        $file = new File('photo1.jpg', 'image/jpeg', 16, $rel, $folder, $owner);
        $this->em->persist($file);

        $media = new Media($file, 'photo');
        $thumbRel = 'public-album/' . uniqid() . '.thumb.jpg';
        file_put_contents($this->storageDir . '/' . $thumbRel, 'thumb contenu');
        $media->setThumbnailPath($thumbRel);
        $this->em->persist($media);

        $album->addMedia($media);
        $this->em->flush();

        return [$album, $media];
    }

    private function createLink(User $owner, Album $album, string $plainToken = 'valid-plain-token'): ShareLink
    {
        $link = new ShareLink(
            $owner,
            Share::RESOURCE_ALBUM,
            $album->getId(),
            bin2hex(random_bytes(16)),
            hash('sha256', $plainToken),
            new \DateTimeImmutable('+7 days'),
        );
        $this->em->persist($link);
        $this->em->flush();

        return $link;
    }

    public function testPublicAlbumPageShowsMediaThumbnailGrid(): void
    {
        $owner = $this->createOwner();
        [$album, $media] = $this->createAlbumWithMedia($owner);
        $link = $this->createLink($owner, $album);

        $crawler = $this->client->request('GET', '/p/' . $link->getSelector() . '/valid-plain-token');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-lightbox]');
        $this->assertSelectorExists('img[src*="/thumbnail"]');
    }

    public function testPublicAlbumPageHasNoSidebarOrActionIcons(): void
    {
        $owner = $this->createOwner();
        [$album, ] = $this->createAlbumWithMedia($owner);
        $link = $this->createLink($owner, $album);

        $crawler = $this->client->request('GET', '/p/' . $link->getSelector() . '/valid-plain-token');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('.hc-sidebar');
        $this->assertSelectorNotExists('[data-testid="share-open-btn"]');
        $this->assertStringContainsString('Vacances', $crawler->filter('body')->text());
    }

    public function testPublicThumbnailStreamsSuccessfully(): void
    {
        $owner = $this->createOwner();
        [$album, $media] = $this->createAlbumWithMedia($owner);
        $link = $this->createLink($owner, $album);

        $this->client->request(
            'GET',
            '/p/' . $link->getSelector() . '/valid-plain-token/media/' . $media->getId()->toRfc4122() . '/thumbnail'
        );

        $this->assertResponseIsSuccessful();
    }

    public function testPublicFullMediaStreamsSuccessfully(): void
    {
        $owner = $this->createOwner();
        [$album, $media] = $this->createAlbumWithMedia($owner);
        $link = $this->createLink($owner, $album);

        $this->client->request(
            'GET',
            '/p/' . $link->getSelector() . '/valid-plain-token/media/' . $media->getId()->toRfc4122() . '/full'
        );

        $this->assertResponseIsSuccessful();
    }

    public function testPublicThumbnailOfMediaOutsideLinkScopeReturns403(): void
    {
        $owner = $this->createOwner();
        [$album, ] = $this->createAlbumWithMedia($owner);
        $link = $this->createLink($owner, $album);

        // Média hors de l'album partagé : ne doit pas être accessible via ce lien.
        $otherFolder = new Folder('Autre', $owner);
        $this->em->persist($otherFolder);
        $otherFile = new File('secret.jpg', 'image/jpeg', 16, 'public-album/other.jpg', $otherFolder, $owner);
        $this->em->persist($otherFile);
        $otherMedia = new Media($otherFile, 'photo');
        $this->em->persist($otherMedia);
        $this->em->flush();

        $this->client->request(
            'GET',
            '/p/' . $link->getSelector() . '/valid-plain-token/media/' . $otherMedia->getId()->toRfc4122() . '/thumbnail'
        );

        $this->assertResponseStatusCodeSame(403);
    }
}
