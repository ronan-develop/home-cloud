<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Album;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels — renommage d'un album (#242).
 * TDD RED → GREEN.
 */
final class AlbumRenameWebTest extends WebTestCase
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

    private function createUser(string $email = 'rename@example.com'): User
    {
        return $this->createWebUser($email, 'secret123', 'Rename User');
    }

    private function createAlbum(User $user, string $name = 'Mon Album'): Album
    {
        $album = new Album($name, $user);
        $this->em->persist($album);
        $this->em->flush();

        return $album;
    }

    private function renameToken(string $albumId): string
    {
        $crawler = $this->client->request('GET', '/albums/' . $albumId);

        return $crawler->filter('form[action*="/rename"] input[name="_token"]')->first()->attr('value');
    }

    public function testRenameRedirectsToAlbumDetail(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $this->loginAs('rename@example.com');
        $token = $this->renameToken($album->getId()->toRfc4122());

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/rename', [
            '_token' => $token,
            'name'   => 'Vacances 2026',
        ]);

        $this->assertResponseRedirects('/albums/' . $album->getId()->toRfc4122());
    }

    public function testRenamePersistsNewNameOnNextPageLoad(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $this->loginAs('rename@example.com');
        $token = $this->renameToken($album->getId()->toRfc4122());

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/rename', [
            '_token' => $token,
            'name'   => 'Vacances 2026',
        ]);

        $crawler = $this->client->request('GET', '/albums/' . $album->getId()->toRfc4122());
        $this->assertStringContainsString('Vacances 2026', $crawler->filter('body')->text());
    }

    public function testRenameWithoutCsrfTokenThrows403(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $this->loginAs('rename@example.com');

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/rename', [
            'name' => 'Vacances 2026',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRenameForbiddenForOtherUser(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $album = $this->createAlbum($alice, 'Album Alice');
        $this->em->flush();
        $bobAlbum = $this->createAlbum($bob, 'Album Bob');
        $this->em->flush();

        $this->loginAs('bob@example.com');
        $token = $this->renameToken($bobAlbum->getId()->toRfc4122());

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/rename', [
            '_token' => $token,
            'name'   => 'Piraté',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRenameWithEmptyNameReturns400(): void
    {
        $user  = $this->createUser();
        $album = $this->createAlbum($user, 'Vacances');
        $this->loginAs('rename@example.com');
        $token = $this->renameToken($album->getId()->toRfc4122());

        $this->client->request('POST', '/albums/' . $album->getId()->toRfc4122() . '/rename', [
            '_token' => $token,
            'name'   => '',
        ]);

        $this->assertResponseStatusCodeSame(400);
    }
}
