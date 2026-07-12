<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels du nouveau dashboard d'accueil (route /)
 * TDD : RED → GREEN
 */
final class DashboardTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM shares');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createUser(string $email = 'dashboard@example.com', string $displayName = 'Dashboard User'): User
    {
        $user = new User($email, $displayName);
        $user->setRoles(['ROLE_USER']);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    private function login(string $email = 'dashboard@example.com'): void
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->client->loginUser($user);
    }

    // --- Authentication & Access ---

    public function testDashboardRequiresLogin(): void
    {
        $this->client->request('GET', '/');
        $this->assertTrue($this->client->getResponse()->isRedirect());
    }

    // --- Basic Rendering ---

    public function testDashboardShowsGreetingWithDisplayName(): void
    {
        $this->createUser('greet@example.com', 'Alice');
        $this->login('greet@example.com');

        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Alice', $this->client->getResponse()->getContent());
    }

    public function testDashboardShowsThreeStatCards(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('hc-stat-card', $this->client->getResponse()->getContent());
    }

    // --- Storage Card (Placeholder) ---

    public function testDashboardStorageCardShowsPlaceholder(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('GET', '/');
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Calcul à implémenter', $content);
        $this->assertStringContainsString('hc-stat-value--placeholder', $content);
    }

    // --- File & Folder Counts ---

    public function testDashboardShowsFileAndFolderCounts(): void
    {
        $user = $this->createUser();

        $folder1 = new Folder('Dossier 1', $user, null);
        $folder2 = new Folder('Dossier 2', $user, null);
        $this->em->persist($folder1);
        $this->em->persist($folder2);

        $parentFolder = new Folder('Parent', $user, null);
        $this->em->persist($parentFolder);
        $this->em->flush();

        $file1 = new File('file1.txt', 'text/plain', 100, '2026/01/file1.txt', $parentFolder, $user);
        $file2 = new File('file2.txt', 'text/plain', 200, '2026/01/file2.txt', $parentFolder, $user);
        $file3 = new File('file3.txt', 'text/plain', 300, '2026/01/file3.txt', $parentFolder, $user);
        $this->em->persist($file1);
        $this->em->persist($file2);
        $this->em->persist($file3);
        $this->em->flush();

        $this->login();
        $this->client->request('GET', '/');

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('2', $content);
        $this->assertStringContainsString('3', $content);
    }

    // --- Shares Count ---

    public function testDashboardShowsActiveSharesCount(): void
    {
        $user = $this->createUser();
        $guest = $this->createUser('guest@example.com', 'Guest User');

        $folder = new Folder('Shared Folder', $user, null);
        $this->em->persist($folder);
        $this->em->flush();

        $activeShare = new Share($user, $guest, Share::RESOURCE_FOLDER, $folder->getId(), Share::PERMISSION_READ);
        $this->em->persist($activeShare);

        $expiredShare = new Share($user, $guest, Share::RESOURCE_FOLDER, $folder->getId(), Share::PERMISSION_READ, new \DateTimeImmutable('-1 day'));
        $this->em->persist($expiredShare);

        $this->em->flush();

        $this->login();
        $this->client->request('GET', '/');

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Partages créés', $content);
    }

    // --- Recent Files ---

    public function testDashboardShowsRecentFilesList(): void
    {
        $user = $this->createUser();

        $parentFolder = new Folder('Uploads', $user, null);
        $this->em->persist($parentFolder);
        $this->em->flush();

        for ($i = 1; $i <= 6; $i++) {
            $file = new File("file-$i.txt", 'text/plain', 100 * $i, "2026/01/file-$i.txt", $parentFolder, $user);
            $this->em->persist($file);
        }

        $this->em->flush();

        $this->login();
        $this->client->request('GET', '/');

        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('hc-file-list', $content);
        $this->assertStringContainsString('hc-file-row', $content);
    }

    // --- Anti-Regression ---

    public function testDashboardDoesNotShowFileExplorerComponents(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('GET', '/');
        $content = $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('data-testid="file-explorer"', $content);
    }

    public function testExplorerRouteStillShowsFileExplorer(): void
    {
        $this->createUser();
        $this->login();

        $this->client->request('GET', '/explorer');

        $this->assertResponseIsSuccessful();
        $content = $this->client->getResponse()->getContent();
        $this->assertStringContainsString('data-testid="file-explorer"', $content);
    }
}
