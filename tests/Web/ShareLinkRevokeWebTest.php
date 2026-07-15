<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\ShareLink;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShareLinkRevokeWebTest extends WebTestCase
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
        $conn->executeStatement('DELETE FROM share_links');
        $conn->executeStatement('DELETE FROM shares');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createOwner(string $email = 'revoke-owner@example.com'): User
    {
        return $this->createWebUser($email, 'secret123', 'Owner');
    }

    private function createLink(User $owner): ShareLink
    {
        $folder = new Folder('Docs', $owner);
        $folder->setVisibility(Folder::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($folder);
        $file = new File('photo.jpg', 'image/jpeg', 1024, 'test/photo.jpg', $folder, $owner);
        $file->setVisibility(File::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($file);
        $this->em->flush();

        $link = new ShareLink(
            $owner,
            Share::RESOURCE_FILE,
            $file->getId(),
            bin2hex(random_bytes(16)),
            hash('sha256', 'valid-plain-token'),
            new \DateTimeImmutable('+7 days'),
        );
        $this->em->persist($link);
        $this->em->flush();

        return $link;
    }

    private function revokeToken(): string
    {
        $crawler = $this->client->request('GET', '/partages');

        return $crawler->filter('form[action*="/share-link-revoke"] input[name="_token"]')->first()->attr('value');
    }

    public function testOwnerCanRevokeTheirLink(): void
    {
        $owner = $this->createOwner();
        $link = $this->createLink($owner);
        $this->loginAs('revoke-owner@example.com');

        $token = $this->revokeToken();

        $this->client->request('POST', '/share-link-revoke', [
            '_token'   => $token,
            'linkId'   => $link->getId()->toRfc4122(),
        ]);

        $this->assertResponseRedirects('/partages');

        // Le lien renvoie 404 à la requête suivante, révocation effective.
        $this->client->request('GET', '/logout');
        $this->client->request('GET', '/p/' . $link->getSelector() . '/valid-plain-token');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testNonOwnerCannotRevokeLink(): void
    {
        $owner = $this->createOwner();
        $link = $this->createLink($owner);
        $attacker = $this->createWebUser('revoke-attacker@example.com', 'secret123', 'Attacker');
        // Le token CSRF est lié à la session : l'attaquant doit avoir son
        // propre lien pour obtenir un token valide dans SA session.
        $this->createLink($attacker);

        $this->loginAs('revoke-attacker@example.com');
        $token = $this->revokeToken();

        $this->client->request('POST', '/share-link-revoke', [
            '_token' => $token,
            'linkId' => $link->getId()->toRfc4122(),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testSharesPageListsShareLinksWithExpirationAndRevokeButton(): void
    {
        $owner = $this->createOwner();
        $link = $this->createLink($owner);
        $this->loginAs('revoke-owner@example.com');

        $crawler = $this->client->request('GET', '/partages');

        $this->assertResponseIsSuccessful();
        $row = $crawler->filter('[data-testid="share-link-row"]')->text();
        $this->assertStringContainsString($link->getExpiresAt()->format('d/m/Y'), $row);
        $this->assertSelectorExists('form[action*="/share-link-revoke"] button[type="submit"]');
    }

    public function testSharesPageShowsThumbnailForLinkOnAlbumWithMedia(): void
    {
        $owner = $this->createOwner();

        $folder = new Folder('Photos', $owner);
        $folder->setVisibility(Folder::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($folder);
        $file = new File('photo.jpg', 'image/jpeg', 1024, 'test/photo.jpg', $folder, $owner);
        $this->em->persist($file);
        $media = new \App\Entity\Media($file, 'photo');
        $media->setThumbnailPath('thumbs/photo.thumb.jpg');
        $this->em->persist($media);

        $album = new \App\Entity\Album('Vacances', $owner);
        $album->setVisibility(\App\Entity\Album::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($album);
        $album->addMedia($media);
        $this->em->flush();

        $link = new ShareLink(
            $owner,
            Share::RESOURCE_ALBUM,
            $album->getId(),
            bin2hex(random_bytes(16)),
            hash('sha256', 'valid-plain-token'),
            new \DateTimeImmutable('+7 days'),
        );
        $this->em->persist($link);
        $this->em->flush();

        $this->loginAs('revoke-owner@example.com');

        $crawler = $this->client->request('GET', '/partages');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="share-link-row"] img[src*="/thumbnail"]');
    }
}
