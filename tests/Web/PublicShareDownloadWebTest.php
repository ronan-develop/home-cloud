<?php

declare(strict_types=1);

namespace App\Tests\Web;

use App\Entity\File;
use App\Entity\Folder;
use App\Entity\Share;
use App\Entity\ShareLink;
use App\Entity\User;
use App\Tests\Web\Fixtures\WebFixturesTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Téléchargement via un lien de partage public.
 *
 * Le test qui compte : un fichier hors du périmètre du lien doit renvoyer
 * 403, pour vérifier qu'on ne peut pas pivoter vers une autre ressource de
 * l'owner en changeant l'id de fichier dans l'URL (même logique IDOR que
 * FileDownloadOwnershipTest côté API authentifiée).
 */
final class PublicShareDownloadWebTest extends WebTestCase
{
    use WebFixturesTrait;

    private EntityManagerInterface $em;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private string $storageDir;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->storageDir = static::getContainer()->getParameter('app.storage_dir');
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

    private function createOwner(string $email = 'public-dl-owner@example.com'): User
    {
        return $this->createWebUser($email, 'secret123', 'Owner');
    }

    private function makeStoredFile(User $owner, Folder $folder, string $name): File
    {
        $rel = 'public-dl/' . uniqid() . '.txt';
        @mkdir($this->storageDir . '/public-dl', 0777, true);
        file_put_contents($this->storageDir . '/' . $rel, 'contenu de test');

        $file = new File($name, 'text/plain', 16, $rel, $folder, $owner);
        $file->setVisibility(File::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($file);
        $this->em->flush();

        return $file;
    }

    private function createLink(User $owner, string $resourceType, \Symfony\Component\Uid\Uuid $resourceId, string $plainToken = 'valid-plain-token'): ShareLink
    {
        $link = new ShareLink(
            $owner,
            $resourceType,
            $resourceId,
            bin2hex(random_bytes(16)),
            hash('sha256', $plainToken),
            new \DateTimeImmutable('+7 days'),
        );
        $this->em->persist($link);
        $this->em->flush();

        return $link;
    }

    public function testHolderOfFileLinkCanDownloadTheFile(): void
    {
        $owner = $this->createOwner();
        $folder = new Folder('Docs', $owner);
        $folder->setVisibility(Folder::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($folder);
        $this->em->flush();
        $file = $this->makeStoredFile($owner, $folder, 'rapport.txt');
        $link = $this->createLink($owner, Share::RESOURCE_FILE, $file->getId());

        $this->client->request(
            'GET',
            '/p/' . $link->getSelector() . '/valid-plain-token/download/' . $file->getId()->toRfc4122()
        );

        $this->assertResponseIsSuccessful();
    }

    public function testHolderOfFolderLinkCanDownloadAFileInsideThatFolder(): void
    {
        $owner = $this->createOwner();
        $folder = new Folder('Docs', $owner);
        $folder->setVisibility(Folder::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($folder);
        $this->em->flush();
        $file = $this->makeStoredFile($owner, $folder, 'rapport.txt');
        $link = $this->createLink($owner, Share::RESOURCE_FOLDER, $folder->getId());

        $this->client->request(
            'GET',
            '/p/' . $link->getSelector() . '/valid-plain-token/download/' . $file->getId()->toRfc4122()
        );

        $this->assertResponseIsSuccessful();
    }

    public function testFileOutsideLinkScopeReturns403(): void
    {
        $owner = $this->createOwner();
        $sharedFolder = new Folder('Partagé', $owner);
        $sharedFolder->setVisibility(Folder::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($sharedFolder);
        $otherFolder = new Folder('Privé', $owner);
        $this->em->persist($otherFolder);
        $this->em->flush();

        $sharedFile = $this->makeStoredFile($owner, $sharedFolder, 'partage.txt');
        $secretFile = $this->makeStoredFile($owner, $otherFolder, 'secret.txt');

        // Le lien porte sur sharedFolder, pas sur otherFolder : secretFile
        // n'est pas dans son périmètre bien qu'appartenant au même owner.
        $link = $this->createLink($owner, Share::RESOURCE_FOLDER, $sharedFolder->getId());

        $this->client->request(
            'GET',
            '/p/' . $link->getSelector() . '/valid-plain-token/download/' . $secretFile->getId()->toRfc4122()
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testWrongTokenReturns404EvenForAFileInScope(): void
    {
        $owner = $this->createOwner();
        $folder = new Folder('Docs', $owner);
        $folder->setVisibility(Folder::VISIBILITY_LINK_ALLOWED);
        $this->em->persist($folder);
        $this->em->flush();
        $file = $this->makeStoredFile($owner, $folder, 'rapport.txt');
        $link = $this->createLink($owner, Share::RESOURCE_FILE, $file->getId());

        $this->client->request(
            'GET',
            '/p/' . $link->getSelector() . '/wrong-token/download/' . $file->getId()->toRfc4122()
        );

        $this->assertResponseStatusCodeSame(404);
    }
}
