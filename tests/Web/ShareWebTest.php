<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ShareWebTest extends WebTestCase
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
        $conn->executeStatement('DELETE FROM shares');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function createUser(string $email = 'shares@example.com'): User
    {
        return $this->createWebUser($email, 'secret123', 'Shares User');
    }

    private function login(string $email = 'shares@example.com'): void
    {
        $this->loginAs($email);
    }

    private function createFile(User $owner, string $name = 'photo.jpg'): File
    {
        $folder = new Folder('Docs', $owner);
        $this->em->persist($folder);
        $file = new File($name, 'image/jpeg', 1024, "test/{$name}", $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    public function testSharesPageRequiresLogin(): void
    {
        $this->client->request('GET', '/partages');

        $this->assertResponseRedirects('/login');
    }

    public function testSharesPageListsOutgoingShares(): void
    {
        $owner = $this->createUser();
        $guest = $this->createUser('guest-out@example.com');
        $file  = $this->createFile($owner, 'partage-sortant.jpg');
        $share = new Share($owner, $guest, Share::RESOURCE_FILE, $file->getId(), Share::PERMISSION_READ);
        $this->em->persist($share);
        $this->em->flush();

        $this->login();

        $crawler = $this->client->request('GET', '/partages');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('partage-sortant.jpg', $crawler->filter('body')->text());
    }

    public function testSharesPageListsIncomingShares(): void
    {
        $owner = $this->createUser('owner-in@example.com');
        $guest = $this->createUser(); // shares@example.com, celui qui se connecte
        $file  = $this->createFile($owner, 'partage-entrant.jpg');
        $share = new Share($owner, $guest, Share::RESOURCE_FILE, $file->getId(), Share::PERMISSION_READ);
        $this->em->persist($share);
        $this->em->flush();

        $this->login();

        $crawler = $this->client->request('GET', '/partages');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('partage-entrant.jpg', $crawler->filter('body')->text());
    }

    public function testSidebarLinkPointsToSharesPage(): void
    {
        $this->createUser();
        $this->login();

        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $link = $crawler->filter('a:contains("Partages")');
        $this->assertGreaterThan(0, $link->count());
        $this->assertSame('/partages', $link->attr('href'));
    }
}
