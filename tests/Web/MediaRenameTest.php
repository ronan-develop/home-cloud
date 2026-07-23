<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels du renommage d'un média (POST /gallery/{id}/rename).
 * Réponse JSON — le renommage se fait en place, sans navigation.
 */
final class MediaRenameTest extends WebTestCase
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

    /**
     * Récupère un token CSRF valide en le lisant depuis /gallery (data-rename-
     * media-csrf-token-value), comme le ferait le navigateur.
     */
    private function csrfToken(): string
    {
        $crawler = $this->client->request('GET', '/gallery');

        return $crawler->filter('[data-testid="media-thumbnail"]')->first()->attr('data-rename-media-csrf-token-value');
    }

    public function testRenameMediaReturnsJsonWithNewName(): void
    {
        $user  = $this->createUser();
        $media = $this->createMediaFile($user, 'ancien-nom.jpg', 'photo');
        $this->login();

        $this->client->request(
            'POST',
            '/gallery/' . $media->getId()->toRfc4122() . '/rename',
            ['name' => 'nouveau-nom.jpg', '_token' => $this->csrfToken()],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('nouveau-nom.jpg', $data['name']);
    }

    public function testRenameMediaPersistsTheNewName(): void
    {
        $user  = $this->createUser();
        $media = $this->createMediaFile($user, 'ancien-nom.jpg', 'photo');
        $this->login();

        $this->client->request(
            'POST',
            '/gallery/' . $media->getId()->toRfc4122() . '/rename',
            ['name' => 'nouveau-nom.jpg', '_token' => $this->csrfToken()],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        );

        $this->client->request('GET', '/gallery');
        $this->assertStringContainsString('nouveau-nom.jpg', $this->client->getResponse()->getContent());
    }

    public function testRenameMediaNotFoundForOtherUser(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob   = $this->createUser('bob@example.com');
        $media = $this->createMediaFile($alice, 'photo.jpg', 'photo');
        // Bob a besoin d'un média à lui pour que /gallery affiche le formulaire
        // porteur du token CSRF (sinon EmptyState est rendu à la place).
        $this->createMediaFile($bob, 'placeholder.jpg', 'photo');

        $this->login('bob@example.com');
        $token = $this->csrfToken();
        $this->client->request(
            'POST',
            '/gallery/' . $media->getId()->toRfc4122() . '/rename',
            ['name' => 'hack.jpg', '_token' => $token],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testRenameMediaWithEmptyNameReturns400(): void
    {
        $user  = $this->createUser();
        $media = $this->createMediaFile($user, 'photo.jpg', 'photo');
        $this->login();

        $this->client->request(
            'POST',
            '/gallery/' . $media->getId()->toRfc4122() . '/rename',
            ['name' => '', '_token' => $this->csrfToken()],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRenameMediaWithForbiddenCharsReturns400(): void
    {
        $user  = $this->createUser();
        $media = $this->createMediaFile($user, 'photo.jpg', 'photo');
        $this->login();

        $this->client->request(
            'POST',
            '/gallery/' . $media->getId()->toRfc4122() . '/rename',
            ['name' => 'inva/lid.jpg', '_token' => $this->csrfToken()],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        );

        $this->assertResponseStatusCodeSame(400);
    }

    public function testRenameMediaWithoutCsrfTokenThrows403(): void
    {
        $user  = $this->createUser();
        $media = $this->createMediaFile($user, 'photo.jpg', 'photo');
        $this->login();

        $this->client->request(
            'POST',
            '/gallery/' . $media->getId()->toRfc4122() . '/rename',
            ['name' => 'nouveau-nom.jpg'],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testRenameDetachedMediaReturns400(): void
    {
        // Media détaché (#246) : plus de File à renommer. Le bouton est
        // masqué côté template, mais l'endpoint doit rester sûr (défense en
        // profondeur). Le token CSRF est lu via un autre média (l'intention
        // 'media-rename' ne dépend pas de la ressource ciblée), le média
        // détaché n'ayant plus l'attribut data-rename-media-csrf-token-value.
        $user  = $this->createUser();
        $this->createMediaFile($user, 'autre.jpg', 'photo');
        $media = $this->createMediaFile($user, 'photo.jpg', 'photo');
        $media->detach();
        $this->em->flush();
        $this->login();

        $this->client->request(
            'POST',
            '/gallery/' . $media->getId()->toRfc4122() . '/rename',
            ['name' => 'nouveau-nom.jpg', '_token' => $this->csrfToken()],
            [],
            ['HTTP_ACCEPT' => 'application/json'],
        );

        $this->assertResponseStatusCodeSame(400);
    }
}
