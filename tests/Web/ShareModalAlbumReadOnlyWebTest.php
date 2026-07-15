<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Album;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'édition en ligne d'un album partagé a été abandonnée : un album partagé
 * est toujours en lecture seule, ShareModal ne doit proposer aucun autre
 * choix de permission pour ce type de ressource. TDD RED → GREEN.
 */
final class ShareModalAlbumReadOnlyWebTest extends WebTestCase
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

    private function createOwner(): User
    {
        return $this->createWebUser('album-readonly-owner@example.com', 'secret123', 'Owner');
    }

    private function createAlbum(User $user): Album
    {
        $album = new Album('Vacances', $user);
        $this->em->persist($album);
        $this->em->flush();

        return $album;
    }

    public function testShareModalForAlbumHasNoWritePermissionOption(): void
    {
        $owner = $this->createOwner();
        $album = $this->createAlbum($owner);
        $this->loginAs('album-readonly-owner@example.com');

        $crawler = $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('input[name="permission"][value="write"]');
    }

    public function testShareModalForAlbumSendsReadPermissionAsHiddenField(): void
    {
        $owner = $this->createOwner();
        $album = $this->createAlbum($owner);
        $this->loginAs('album-readonly-owner@example.com');

        $crawler = $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());

        $this->assertResponseIsSuccessful();
        $hiddenPermission = $crawler->filter('form[action*="/share-create"] input[type="hidden"][name="permission"]');
        $this->assertGreaterThan(0, $hiddenPermission->count());
        $this->assertSame('read', $hiddenPermission->attr('value'));
    }
}
