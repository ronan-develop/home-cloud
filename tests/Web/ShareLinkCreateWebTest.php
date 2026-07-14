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

final class ShareLinkCreateWebTest extends WebTestCase
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
        return $this->createWebUser('sharelink-owner@example.com', 'secret123', 'Owner');
    }

    private function createFile(User $owner, string $visibility, string $name = 'photo.jpg'): File
    {
        $folder = new Folder('Docs', $owner);
        $folder->setVisibility(Folder::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($folder);
        $file = new File($name, 'image/jpeg', 1024, "test/{$name}", $folder, $owner);
        $file->setVisibility($visibility);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    private function shareLinkCreateToken(): string
    {
        $crawler = $this->client->request('GET', '/explorer');

        return $crawler->filter('form[action*="/share-link-create"] input[name="_token"]')->first()->attr('value');
    }

    public function testCreateShareLinkOnPubliclyShareableFileRedirectsWithLink(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile($owner, File::VISIBILITY_LINK_ALLOWED);
        $this->loginAs('sharelink-owner@example.com');

        $token = $this->shareLinkCreateToken();

        $this->client->request('POST', '/share-link-create', [
            '_token'       => $token,
            'resourceType' => 'file',
            'resourceId'   => $file->getId()->toRfc4122(),
        ]);

        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.flash-success', '/p/');

        $link = $this->em->getRepository(ShareLink::class)->findOneBy(['resourceId' => $file->getId()]);
        $this->assertNotNull($link);
    }

    public function testCreateShareLinkOnPrivateFileReturns403(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile($owner, File::VISIBILITY_PRIVATE);
        $this->loginAs('sharelink-owner@example.com');

        $token = $this->shareLinkCreateToken();

        $this->client->request('POST', '/share-link-create', [
            '_token'       => $token,
            'resourceType' => 'file',
            'resourceId'   => $file->getId()->toRfc4122(),
        ]);

        $this->assertResponseStatusCodeSame(403);

        $link = $this->em->getRepository(ShareLink::class)->findOneBy(['resourceId' => $file->getId()]);
        $this->assertNull($link);
    }

    public function testCreateShareLinkWithInvalidCsrfTokenReturns403(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile($owner, File::VISIBILITY_LINK_ALLOWED);
        $this->loginAs('sharelink-owner@example.com');

        $this->client->request('POST', '/share-link-create', [
            '_token'       => 'invalid-token',
            'resourceType' => 'file',
            'resourceId'   => $file->getId()->toRfc4122(),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateShareLinkByNonOwnerReturns403(): void
    {
        $owner = $this->createOwner();
        $file = $this->createFile($owner, File::VISIBILITY_LINK_ALLOWED);
        $attacker = $this->createWebUser('sharelink-attacker@example.com', 'secret123', 'Attacker');
        // Le token CSRF est lié à la session : l'attaquant doit le récupérer
        // dans SA propre session, via sa propre ressource partageable.
        $attackerFolder = new Folder('AttackerDocs', $attacker);
        $attackerFolder->setVisibility(Folder::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($attackerFolder);
        $this->em->flush();

        $this->loginAs('sharelink-attacker@example.com');
        $token = $this->shareLinkCreateToken();

        $this->client->request('POST', '/share-link-create', [
            '_token'       => $token,
            'resourceType' => 'file',
            'resourceId'   => $file->getId()->toRfc4122(),
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
