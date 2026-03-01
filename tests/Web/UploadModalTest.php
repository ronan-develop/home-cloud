<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\Folder;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Tests fonctionnels du composant upload modal (Phase 7C+).
 * Couvre : token bridge, menu "Nouveau", création dossier, import fichier.
 */
final class UploadModalTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createUser(string $email = 'modal@example.com', string $password = 'secret123'): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User($email, 'ModalUser');
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function login(string $email = 'modal@example.com', string $password = 'secret123'): void
    {
        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            'email'    => $email,
            'password' => $password,
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();
    }

    // --- Token bridge ---

    public function testWebTokenRequiresSession(): void
    {
        $this->client->request('GET', '/api/web/token');
        $this->assertResponseRedirects('/login');
    }

    public function testWebTokenReturnsJwt(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('GET', '/api/web/token');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
        // JWT format : 3 parties séparées par des points
        $this->assertCount(3, explode('.', $data['token']));
    }

    // --- Sidebar ---

    public function testSidebarHasNouveauButton(): void
    {
        $this->createUser();
        $this->login();

        $this->assertSelectorExists('[data-testid="nouveau-btn"]');
    }

    public function testSidebarNouveauMenuHasOptions(): void
    {
        $this->createUser();
        $this->login();

        $this->assertSelectorExists('[data-testid="nouveau-menu"]');
        $this->assertSelectorTextContains('[data-testid="nouveau-menu"]', 'Nouveau dossier');
        $this->assertSelectorTextContains('[data-testid="nouveau-menu"]', 'Importer un fichier');
    }

    // --- Dossiers disponibles dans le modal ---

    public function testUploadModalListsUserFolders(): void
    {
        $user = $this->createUser();

        $folder = new Folder('Vacances', $user);
        $this->em->persist($folder);
        $this->em->flush();

        $this->login();

        $this->assertSelectorTextContains('[data-testid="upload-folder-list"]', 'Vacances');
    }
}
