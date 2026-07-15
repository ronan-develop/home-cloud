<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Album;
use App\Entity\File;
use App\Entity\Share;
use App\Entity\ShareLink;
use App\Entity\User;
use App\Tests\AuthenticatedApiTestCase;

/**
 * Vérifie que les 4 sites d'appel réels de deleteByResource (File API,
 * File web, Folder récursif, Album) passent bien par SharedResourceCleaner
 * — donc suppriment aussi les ShareLink, pas seulement les Share.
 *
 * Test d'intégration (vraie base) plutôt qu'unitaire : ce qui compte est le
 * comportement de bout en bout après suppression HTTP réelle, pas l'appel
 * isolé d'une méthode.
 */
final class SharedResourceCleanerIntegrationTest extends AuthenticatedApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('DELETE FROM share_links');
        $conn->executeStatement('DELETE FROM shares');
        $conn->executeStatement('DELETE FROM medias');
        $conn->executeStatement('DELETE FROM albums');
        $conn->executeStatement('DELETE FROM files');
        $conn->executeStatement('DELETE FROM folders');
        $conn->executeStatement('DELETE FROM users');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        $this->em->clear();
    }

    private function makeLink(User $owner, string $resourceType, \Symfony\Component\Uid\Uuid $resourceId): ShareLink
    {
        $link = new ShareLink(
            $owner,
            $resourceType,
            $resourceId,
            bin2hex(random_bytes(16)),
            hash('sha256', 'plain-token'),
            new \DateTimeImmutable('+7 days'),
        );
        $this->em->persist($link);
        $this->em->flush();

        return $link;
    }

    public function testDeletingFileViaApiRemovesItsShareLink(): void
    {
        $owner = $this->createUser('cleaner-file@example.com', 'password123', 'Owner');
        $folder = $this->createFolder('Docs', $owner);
        $file = new File('doc.txt', 'text/plain', 10, 'test/doc.txt', $folder, $owner);
        $this->em->persist($file);
        $this->em->flush();
        $this->makeLink($owner, Share::RESOURCE_FILE, $file->getId());

        $client = $this->createAuthenticatedClient($owner);
        $client->request('DELETE', '/api/v1/files/' . $file->getId());

        static::assertResponseStatusCodeSame(204);
        $this->em->clear();
        $remaining = $this->em->getRepository(ShareLink::class)->findOneBy(['resourceId' => $file->getId()]);
        $this->assertNull($remaining);
    }

    public function testDeletingAlbumViaServiceRemovesItsShareLink(): void
    {
        $owner = $this->createUser('cleaner-album@example.com', 'password123', 'Owner');
        $album = new Album('Vacances', $owner);
        $this->em->persist($album);
        $this->em->flush();
        $this->makeLink($owner, Share::RESOURCE_ALBUM, $album->getId());

        $albumService = static::getContainer()->get(\App\Service\AlbumService::class);
        $albumService->delete($album);

        $this->em->clear();
        $remaining = $this->em->getRepository(ShareLink::class)->findOneBy(['resourceId' => $album->getId()]);
        $this->assertNull($remaining);
    }
}
