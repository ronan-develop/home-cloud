<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Sélection directe d'un invité déjà créé depuis ShareModal, pour éviter
 * de ressaisir son email à chaque partage. TDD RED → GREEN.
 */
final class ShareModalGuestSelectionWebTest extends WebTestCase
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

    private function createOwner(): User
    {
        return $this->createWebUser('modal-guest-owner@example.com', 'secret123', 'Owner');
    }

    private function createGuest(string $email = 'invite@example.com', string $displayName = 'Invite'): User
    {
        $guest = new User($email, $displayName);
        $guest->markAsGuest();
        $this->em->persist($guest);
        $this->em->flush();

        return $guest;
    }

    private function createFile(User $owner): Folder
    {
        $folder = new Folder('Docs', $owner);
        $this->em->persist($folder);
        $file = new File('photo.jpg', 'image/jpeg', 1024, 'test/photo.jpg', $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();

        return $folder;
    }

    public function testShareModalListsExistingGuestsForSelection(): void
    {
        $owner = $this->createOwner();
        $folder = $this->createFile($owner);
        $this->createGuest('alice@example.com', 'Alice');
        $this->loginAs('modal-guest-owner@example.com');

        $crawler = $this->client->request('GET', '/explorer?folder=' . $folder->getId()->toRfc4122());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="share-guest-option"]');
        $this->assertSelectorTextContains('[data-testid="share-guest-option"]', 'Alice');
    }

    public function testShareModalDoesNotListOwnerAsGuestOption(): void
    {
        $owner = $this->createOwner();
        $folder = $this->createFile($owner);
        $this->loginAs('modal-guest-owner@example.com');

        $crawler = $this->client->request('GET', '/explorer?folder=' . $folder->getId()->toRfc4122());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-testid="share-guest-option"]');
    }

    public function testShareModalShowsEmptyStateHintWhenNoGuestsExist(): void
    {
        $owner = $this->createOwner();
        $folder = $this->createFile($owner);
        $this->loginAs('modal-guest-owner@example.com');

        $crawler = $this->client->request('GET', '/explorer?folder=' . $folder->getId()->toRfc4122());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="share-no-guests-hint"]');
    }
}
