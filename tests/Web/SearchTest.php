<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels pour la recherche XHR.
 * TDD RED → GREEN
 */
final class SearchTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = static::getContainer()->get(EntityManagerInterface::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->user = new User('search@example.com', 'Searcher');
        $this->user->setPassword($hasher->hashPassword($this->user, 'secret123'));
        $this->em->persist($this->user);
        $this->em->flush();

        // Fixtures : 1 folder "Projets", 1 file "rapport-annuel.pdf"
        $folder = new Folder('Projets', $this->user);
        $this->em->persist($folder);
        $file = new File('rapport-annuel.pdf', 'application/pdf', 2048, '2026/03/test.pdf', $folder, $this->user);
        $this->em->persist($file);
        $this->em->flush();

        // Login
        $crawler = $this->client->request('GET', '/login');
        $form    = $crawler->selectButton('Se connecter')->form([
            'email'    => 'search@example.com',
            'password' => 'secret123',
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    public function testSearchRequiresAuthentication(): void
    {
        // Redémarre le client pour effacer la session (simule un anonyme)
        $this->client->restart();
        $this->client->request('GET', '/search?q=test');
        $this->assertResponseRedirects('/login');
    }

    public function testSearchEmptyQueryReturnsEmptyItems(): void
    {
        $this->client->request('GET', '/search?q=', [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertEmpty($data['items']);
    }

    public function testSearchFindsMatchingFolder(): void
    {
        $this->client->request('GET', '/search?q=Projet', [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseIsSuccessful();
        $data  = json_decode($this->client->getResponse()->getContent(), true);
        $names = array_column($data['items'], 'name');
        $this->assertContains('Projets', $names);
    }

    public function testSearchFindsMatchingFile(): void
    {
        $this->client->request('GET', '/search?q=rapport', [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseIsSuccessful();
        $data  = json_decode($this->client->getResponse()->getContent(), true);
        $names = array_column($data['items'], 'name');
        $this->assertContains('rapport-annuel.pdf', $names);
    }

    public function testSearchReturnsNoResultsForUnknownQuery(): void
    {
        $this->client->request('GET', '/search?q=zzznomatch', [], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEmpty($data['items']);
    }

    public function testSearchDoesNotReturnOtherUsersData(): void
    {
        // Crée un autre user avec un dossier "Secret"
        $hasher  = static::getContainer()->get(UserPasswordHasherInterface::class);
        $other   = new User('other@example.com', 'Other');
        $other->setPassword($hasher->hashPassword($other, 'secret123'));
        $this->em->persist($other);
        $folderOther = new Folder('SecretOther', $other);
        $this->em->persist($folderOther);
        $this->em->flush();

        $this->client->request('GET', '/search?q=SecretOther', [], [], ['HTTP_ACCEPT' => 'application/json']);
        $data  = json_decode($this->client->getResponse()->getContent(), true);
        $names = array_column($data['items'], 'name');
        $this->assertNotContains('SecretOther', $names);
    }
}
