<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels de la suppression définitive en masse
 * (POST /gallery/bulk-delete), utilisée par la sélection multiple de la
 * galerie.
 */
final class MediaBulkDeleteTest extends WebTestCase
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

    public function testBulkDeleteRemovesAllSelectedMedias(): void
    {
        $user   = $this->createUser();
        $mediaA = $this->createMediaFile($user, 'photo-a.jpg', 'photo');
        $mediaB = $this->createMediaFile($user, 'photo-b.jpg', 'photo');
        $this->login();

        $this->client->request('POST', '/gallery/bulk-delete', [
            'mediaIds' => [$mediaA->getId()->toRfc4122(), $mediaB->getId()->toRfc4122()],
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(2, $data['deletedCount']);

        $this->client->request('GET', '/gallery');
        $content = $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('photo-a.jpg', $content);
        $this->assertStringNotContainsString('photo-b.jpg', $content);
    }

    public function testBulkDeleteIgnoresMediasNotOwnedByUser(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $aliceMedia = $this->createMediaFile($alice, 'photo-alice.jpg', 'photo');
        $bobMedia   = $this->createMediaFile($bob, 'photo-bob.jpg', 'photo');

        $this->login('bob@example.com');
        $this->client->request('POST', '/gallery/bulk-delete', [
            'mediaIds' => [$aliceMedia->getId()->toRfc4122(), $bobMedia->getId()->toRfc4122()],
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(1, $data['deletedCount']);

        $this->client->request('GET', '/logout');
        $this->login('alice@example.com');
        $this->client->request('GET', '/gallery');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('photo-alice.jpg', $this->client->getResponse()->getContent());
    }

    public function testBulkDeleteWithEmptySelectionReturnsZero(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('POST', '/gallery/bulk-delete', ['mediaIds' => []]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(0, $data['deletedCount']);
    }

    public function testBulkDeleteRequiresAuthentication(): void
    {
        $user  = $this->createUser();
        $media = $this->createMediaFile($user, 'photo.jpg', 'photo');

        $this->client->request('POST', '/gallery/bulk-delete', [
            'mediaIds' => [$media->getId()->toRfc4122()],
        ]);

        $this->assertResponseRedirects('/login');
    }
}
