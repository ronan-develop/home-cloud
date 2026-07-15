<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\ShareLink;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PublicShareWebTest extends WebTestCase
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
        return $this->createWebUser('public-share-owner@example.com', 'secret123', 'Owner');
    }

    private function createSharedFile(User $owner, string $name = 'photo.jpg'): File
    {
        $folder = new Folder('Docs', $owner);
        $folder->setVisibility(Folder::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($folder);
        $file = new File($name, 'image/jpeg', 1024, "test/{$name}", $folder, $owner);
        $file->setVisibility(File::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    private function createLink(User $owner, File $file, string $plainToken = 'valid-plain-token', ?\DateTimeImmutable $expiresAt = null): ShareLink
    {
        $link = new ShareLink(
            $owner,
            \App\Entity\Share::RESOURCE_FILE,
            $file->getId(),
            bin2hex(random_bytes(16)),
            hash('sha256', $plainToken),
            $expiresAt ?? new \DateTimeImmutable('+7 days'),
        );
        $this->em->persist($link);
        $this->em->flush();

        return $link;
    }

    public function testValidLinkIsAccessibleWithoutBeingLoggedIn(): void
    {
        $owner = $this->createOwner();
        $file  = $this->createSharedFile($owner);
        $link  = $this->createLink($owner, $file);

        $this->client->request('GET', '/p/' . $link->getSelector() . '/valid-plain-token');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($file->getOriginalName(), $this->client->getResponse()->getContent());
    }

    public function testWrongTokenReturns404(): void
    {
        $owner = $this->createOwner();
        $file  = $this->createSharedFile($owner);
        $link  = $this->createLink($owner, $file);

        $this->client->request('GET', '/p/' . $link->getSelector() . '/wrong-token');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testUnknownSelectorReturns404(): void
    {
        $this->client->request('GET', '/p/00000000000000000000000000000000/whatever-token');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testExpiredLinkReturns404(): void
    {
        $owner = $this->createOwner();
        $file  = $this->createSharedFile($owner);
        $link  = $this->createLink($owner, $file, expiresAt: new \DateTimeImmutable('-1 second'));

        $this->client->request('GET', '/p/' . $link->getSelector() . '/valid-plain-token');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testRevokedLinkReturns404(): void
    {
        $owner = $this->createOwner();
        $file  = $this->createSharedFile($owner);
        $link  = $this->createLink($owner, $file);
        $link->revoke();
        $this->em->flush();

        $this->client->request('GET', '/p/' . $link->getSelector() . '/valid-plain-token');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testResponseHasNoindexHeader(): void
    {
        $owner = $this->createOwner();
        $file  = $this->createSharedFile($owner);
        $link  = $this->createLink($owner, $file);

        $this->client->request('GET', '/p/' . $link->getSelector() . '/valid-plain-token');

        $this->assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
    }

    public function testPageDoesNotExposeAnyWriteAction(): void
    {
        $owner = $this->createOwner();
        $file  = $this->createSharedFile($owner);
        $link  = $this->createLink($owner, $file);

        $crawler = $this->client->request('GET', '/p/' . $link->getSelector() . '/valid-plain-token');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('form');
        $this->assertSelectorNotExists('[data-testid*="rename"]');
        $this->assertSelectorNotExists('[data-testid*="delete"]');
    }
}
