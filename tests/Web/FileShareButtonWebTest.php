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
 * Un fichier isolé (hors album) n'avait aucun bouton de partage dans
 * l'explorateur, alors que le backend (ShareLinkFactory, Share entre
 * comptes) supporte Share::RESOURCE_FILE depuis le début du chantier.
 */
final class FileShareButtonWebTest extends WebTestCase
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
        return $this->createWebUser('file-share-owner@example.com', 'secret123', 'Owner');
    }

    private function createFile(User $owner): array
    {
        $folder = new Folder('Docs', $owner);
        $this->em->persist($folder);
        $file = new File('photo.jpg', 'image/jpeg', 1024, 'test/photo.jpg', $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();

        return [$file, $folder];
    }

    public function testExplorerHasShareButtonAndModalForAStandaloneFile(): void
    {
        $owner = $this->createOwner();
        [$file, $folder] = $this->createFile($owner);
        $this->loginAs('file-share-owner@example.com');

        $this->client->request('GET', '/explorer?folder=' . $folder->getId()->toRfc4122());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-testid="share-file-btn-' . $file->getId() . '"]');
        $this->assertSelectorExists('form[action*="/share-create"] input[value="file"]');
    }
}
